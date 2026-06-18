<?php

namespace App\Http\Middleware;

use App\Models\Credential;
use App\Support\Sdk\SdkContext;
use App\Support\Sdk\SdkError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the registered MAAC application behind an SDK/runtime request from
 * the Passport client_credentials token, and binds an {@see SdkContext} to the
 * request. Rejects tokens with no matching credential and revoked credentials.
 */
class AuthenticateSdkClient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $client = $this->resolveClient();

        if (! $client instanceof Client) {
            return SdkError::response('invalid_token', 'A valid client access token is required.', 401);
        }

        $credential = Credential::query()
            ->where('oauth_client_id', $client->getKey())
            ->whereHas('application')
            ->with('application.team')
            ->first();

        if ($credential === null) {
            return SdkError::response('unknown_client', 'The access token is not associated with a registered application.', 401);
        }

        if (! $credential->isUsable()) {
            return SdkError::response('credential_revoked', 'This credential has been revoked.', 403);
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('sdk_context', new SdkContext(
            $credential,
            $credential->application,
            $credential->environment,
        ));

        return $next($request);
    }

    /**
     * Resolve the Passport client that owns the request's access token.
     */
    private function resolveClient(): ?Client
    {
        $guard = Auth::guard('api');

        return method_exists($guard, 'client') ? $guard->client() : null;
    }
}
