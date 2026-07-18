<?php

namespace App\Support\Outbound;

class OutboundDestination
{
    /**
     * @param  array<int, string>  $resolvedIps
     */
    public function __construct(
        public readonly string $url,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
        public readonly array $resolvedIps,
        public readonly string $pinnedIp,
    ) {}

    /**
     * Pin the validated host to the validated address and disable automatic
     * redirects so every hop re-enters the policy.
     *
     * @return array<string, mixed>
     */
    public function guzzleOptions(): array
    {
        $curlResolve = defined('CURLOPT_RESOLVE') ? constant('CURLOPT_RESOLVE') : 10203;

        return [
            'allow_redirects' => false,
            'proxy' => '',
            'curl' => [
                $curlResolve => ["{$this->host}:{$this->port}:{$this->pinnedIp}"],
            ],
        ];
    }
}
