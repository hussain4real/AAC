<?php

declare(strict_types=1);

namespace Maac\Sdk\Auth;

use Closure;
use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\Exceptions\MaacApiException;
use Maac\Sdk\Http\HttpRequest;
use Maac\Sdk\MaacConfig;

/**
 * Exchanges the application credential for a short-lived OAuth2 access token via
 * MAAC's `/oauth/token` endpoint (the client_credentials grant), caching it in
 * memory and transparently refreshing once it nears expiry.
 */
final class TokenProvider
{
    private ?AccessToken $token = null;

    /**
     * @var Closure(): int
     */
    private readonly Closure $clock;

    /**
     * @param  (Closure(): int)|null  $clock  Returns the current epoch second; injectable for tests.
     */
    public function __construct(
        private readonly MaacConfig $config,
        private readonly Transport $transport,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Return a valid bearer token, exchanging or refreshing as needed.
     */
    public function token(): string
    {
        $now = ($this->clock)();

        if ($this->token === null || $this->token->isExpired($now)) {
            $this->token = $this->exchange();
        }

        return $this->token->token;
    }

    /**
     * Force a fresh token exchange, discarding any cached token. Used to recover
     * from a token that MAAC rejected mid-flight.
     */
    public function refresh(): string
    {
        $this->token = $this->exchange();

        return $this->token->token;
    }

    /**
     * Perform the client_credentials exchange.
     *
     * @throws MaacApiException when MAAC rejects the credential
     */
    private function exchange(): AccessToken
    {
        $response = $this->transport->send(HttpRequest::form('POST', $this->config->url('/oauth/token'), [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
        ]));

        if (! $response->successful()) {
            throw MaacApiException::fromResponse($response);
        }

        return AccessToken::fromTokenResponse($response->json(), ($this->clock)());
    }
}
