<?php

namespace App\Http\Controllers;

use App\Enums\SsoFailureCode;
use App\Models\SsoConnection;
use App\Support\Sso\SsoAuthenticator;
use App\Support\Sso\SsoException;
use App\Support\Sso\SsoSecurityEventRecorder;
use App\Support\Sso\SsoUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Enterprise SSO login: redirects a guest to the provider's authorize endpoint
 * (with a CSRF state in the session) and handles the callback — verifying state,
 * exchanging the code, mapping the identity onto a MAACC user/role, and signing
 * them in. Local password auth remains available alongside this.
 */
class SsoController extends Controller
{
    /**
     * Begin the SSO login by redirecting to the provider's authorize endpoint.
     */
    public function redirect(Request $request, SsoConnection $ssoConnection, SsoAuthenticator $authenticator): RedirectResponse
    {
        abort_unless($ssoConnection->isActive(), 404);

        $state = Str::random(40);
        $nonce = Str::random(48);
        $codeVerifier = Str::random(96);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $flows = $request->session()->get('sso.flows', []);
        $flows = is_array($flows) ? array_slice($flows, -4, null, true) : [];
        $flows[$state] = [
            'state' => $state,
            'connection_id' => $ssoConnection->id,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'created_at' => now()->timestamp,
        ];
        $request->session()->put('sso.flows', $flows);

        return redirect()->away($authenticator->authorizeUrl($ssoConnection, $state, $nonce, $codeChallenge));
    }

    /**
     * Handle the provider callback: verify state, exchange the code, resolve the
     * user, and sign them in.
     */
    public function callback(Request $request, SsoConnection $ssoConnection, SsoAuthenticator $authenticator, SsoUserResolver $resolver, SsoSecurityEventRecorder $securityEvents): RedirectResponse
    {
        abort_unless($ssoConnection->isActive(), 404);

        $state = $request->query('state');
        $flow = is_string($state) ? $request->session()->pull('sso.flows.'.$state) : null;
        $expected = is_array($flow) ? ($flow['state'] ?? null) : null;
        $expectedConnection = is_array($flow) ? ($flow['connection_id'] ?? null) : null;

        if (! is_string($state)
            || ! is_string($expected)
            || ! hash_equals($expected, $state)
            || $expectedConnection !== $ssoConnection->id) {
            return $this->reject($ssoConnection, $request, $securityEvents, SsoFailureCode::StateOrConnectionMismatch);
        }

        $createdAt = $flow['created_at'] ?? null;
        $flowTtl = max(60, (int) config('maacc.sso.flow_ttl_seconds', 600));

        if (! is_numeric($createdAt) || now()->getTimestamp() - (int) $createdAt > $flowTtl) {
            return $this->reject($ssoConnection, $request, $securityEvents, SsoFailureCode::FlowExpired);
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->reject($ssoConnection, $request, $securityEvents, SsoFailureCode::AuthorizationCodeMissing);
        }

        try {
            $nonce = $flow['nonce'] ?? null;
            $codeVerifier = $flow['code_verifier'] ?? null;

            if (! is_string($nonce) || ! is_string($codeVerifier)) {
                throw new SsoException('the single sign-on transaction had expired', SsoFailureCode::FlowExpired);
            }

            $payload = $authenticator->exchange($ssoConnection, $code, $codeVerifier, $nonce);
            $user = $resolver->resolve($ssoConnection, $payload);
        } catch (SsoException $exception) {
            return $this->reject($ssoConnection, $request, $securityEvents, $exception->failureCode);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', ['current_team' => $user->currentTeam?->slug]));
    }

    private function reject(SsoConnection $connection, Request $request, SsoSecurityEventRecorder $securityEvents, SsoFailureCode $code): RedirectResponse
    {
        $correlationId = $securityEvents->record($connection, $request, $code);

        return redirect()->route('login')->withErrors([
            'sso' => 'Single sign-on could not be completed. Reference: '.$correlationId.'.',
        ]);
    }
}
