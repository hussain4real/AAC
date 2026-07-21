<?php

namespace Tests\Support\Outbound;

use App\Support\Outbound\DnsResolver;

class FakeDnsResolver implements DnsResolver
{
    /** @param  array<string, array<int, string>>  $records */
    public function __construct(private array $records = ['*' => ['1.1.1.1']]) {}

    /** @param  array<int, string>  $addresses */
    public function set(string $host, array $addresses): void
    {
        $this->records[strtolower($host)] = $addresses;
    }

    /** @return array<int, string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->records[strtolower($host)] ?? $this->records['*'] ?? [];
    }
}
