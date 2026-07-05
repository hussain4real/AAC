<?php

namespace App\Http\Controllers;

use App\Models\SsoConnection;
use App\Support\Sso\SsoAuthenticator;
use App\Support\Sso\SsoException;
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
        $request->session()->put('sso.state', $state);
        $request->session()->put('sso.connection', $ssoConnection->id);

        return redirect()->away($authenticator->authorizeUrl($ssoConnection, $state, Str::random(40)));
    }

    /**
     * Handle the provider callback: verify state, exchange the code, resolve the
     * user, and sign them in.
     */
    public function callback(Request $request, SsoConnection $ssoConnection, SsoAuthenticator $authenticator, SsoUserResolver $resolver): RedirectResponse
    {
        abort_unless($ssoConnection->isActive(), 404);

        $state = $request->query('state');
        $expected = $request->session()->pull('sso.state');

        if (! is_string($state) || ! is_string($expected) || ! hash_equals($expected, $state)) {
            return redirect()->route('login')->withErrors(['sso' => 'The single sign-on request could not be verified. Please try again.']);
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()->route('login')->withErrors(['sso' => 'The identity provider did not return an authorization code.']);
        }

        try {
            $payload = $authenticator->exchange($ssoConnection, $code);
            $user = $resolver->resolve($ssoConnection, $payload);
        } catch (SsoException $exception) {
            return redirect()->route('login')->withErrors(['sso' => 'Single sign-on failed: '.$exception->getMessage().'.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', ['current_team' => $user->currentTeam?->slug]));
    }
}
