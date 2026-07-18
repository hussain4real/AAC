<?php

namespace App\Support\Outbound;

use App\Exceptions\OutboundRequestBlocked;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class OutboundRequestPolicy
{
    public function __construct(private readonly DnsResolver $dns) {}

    /**
     * Validate and resolve one destination immediately before connection.
     *
     * @param  array<int, string>  $allowedHosts
     *
     * @throws OutboundRequestBlocked
     */
    public function inspect(string $url, string $purpose, array $allowedHosts = []): OutboundDestination
    {
        if (app()->isProduction() && ! (bool) config('maacc.outbound.infrastructure_enforced', false)) {
            throw new OutboundRequestBlocked('production network egress controls have not been attested');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new OutboundRequestBlocked('the destination is not a valid URL');
        }

        $scheme = Str::lower($parts['scheme']);
        $host = $this->normalizeHost($parts['host']);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new OutboundRequestBlocked('only HTTP(S) destinations with a host are supported');
        }

        if (array_key_exists('user', $parts) || array_key_exists('pass', $parts)) {
            throw new OutboundRequestBlocked('URL userinfo is not permitted');
        }

        if ($parts['host'] !== rtrim($parts['host'], '.')) {
            throw new OutboundRequestBlocked('a trailing-dot hostname is not permitted');
        }

        if ((app()->isProduction() || (bool) config('maacc.outbound.require_https', false)) && $scheme !== 'https') {
            throw new OutboundRequestBlocked('HTTPS is required for this environment');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $allowedPorts = array_map('intval', (array) config(
            "maacc.outbound.purposes.{$purpose}.allowed_ports",
            app()->isProduction() ? [443] : [80, 443],
        ));

        if (! in_array($port, $allowedPorts, true)) {
            throw new OutboundRequestBlocked("port {$port} is not approved for {$purpose}");
        }

        if ($this->isAmbiguousNumericHost($host)) {
            throw new OutboundRequestBlocked('alternate numeric address encodings are not permitted');
        }

        if ($this->isBlockedHostname($host)) {
            throw new OutboundRequestBlocked("host [{$host}] is reserved or local");
        }

        $configuredHosts = (array) config("maacc.outbound.purposes.{$purpose}.allowed_hosts", []);
        $hostPatterns = $allowedHosts !== [] ? $allowedHosts : $configuredHosts;

        if ($hostPatterns === [] && (bool) config("maacc.outbound.purposes.{$purpose}.require_allowlist", false)) {
            throw new OutboundRequestBlocked("no destination hosts are approved for {$purpose}");
        }

        if ($hostPatterns !== [] && ! $this->matchesAny($host, $hostPatterns)) {
            throw new OutboundRequestBlocked("host [{$host}] is not approved for {$purpose}");
        }

        $resolvedIps = $this->dns->resolve($host);

        if ($resolvedIps === []) {
            throw new OutboundRequestBlocked("host [{$host}] did not resolve to an address");
        }

        foreach ($resolvedIps as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new OutboundRequestBlocked("host [{$host}] resolves to a non-public address");
            }
        }

        sort($resolvedIps, SORT_STRING);

        return new OutboundDestination($url, $scheme, $host, $port, $resolvedIps, $resolvedIps[0]);
    }

    private function normalizeHost(string $host): string
    {
        $host = Str::lower(trim($host, '[]'));

        if (preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $host = is_string($ascii) ? Str::lower($ascii) : '';
        }

        return $host;
    }

    private function isAmbiguousNumericHost(string $host): bool
    {
        if (preg_match('/^\d+$/', $host) === 1) {
            return true;
        }

        $labels = explode('.', $host);

        return count($labels) > 1 && collect($labels)->every(
            fn (string $label): bool => preg_match('/^(?:\d+|0x[0-9a-f]+)$/i', $label) === 1,
        ) && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false;
    }

    private function isBlockedHostname(string $host): bool
    {
        $blocked = [
            'localhost',
            'metadata.google.internal',
            'metadata.goog',
            'instance-data.ec2.internal',
            'kubernetes.default.svc',
        ];

        return in_array($host, $blocked, true)
            || Str::endsWith($host, ['.localhost', '.local', '.internal', '.svc']);
    }

    /** @param  array<int, string>  $patterns */
    private function matchesAny(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = Str::lower(trim($pattern));

            if ($pattern === '*') {
                return true;
            }

            if (Str::startsWith($pattern, '*.') && Str::endsWith($host, Str::substr($pattern, 1)) && $host !== Str::substr($pattern, 2)) {
                return true;
            }

            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach ([
            '0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
            '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b:1::/48', '100::/64',
            '2001:2::/48', '2001:10::/28', '2001:db8::/32', '2002::/16',
            'fc00::/7', 'fe80::/10', 'ff00::/8',
        ] as $range) {
            if (IpUtils::checkIp($ip, $range)) {
                return false;
            }
        }

        return true;
    }
}
