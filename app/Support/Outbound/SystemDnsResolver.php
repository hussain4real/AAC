<?php

namespace App\Support\Outbound;

use Closure;

class SystemDnsResolver implements DnsResolver
{
    /** @param (Closure(string, int): (array<int, array<string, mixed>>|false))|null $lookup */
    public function __construct(private readonly ?Closure $lookup = null) {}

    /** @return array<int, string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = $this->lookup instanceof Closure
            ? ($this->lookup)($host, DNS_A | DNS_AAAA)
            : dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->flatMap(fn (array $record): array => array_values(array_filter([
                is_string($record['ip'] ?? null) ? $record['ip'] : null,
                is_string($record['ipv6'] ?? null) ? $record['ipv6'] : null,
            ])))
            ->unique()
            ->values()
            ->all();
    }
}
