<?php

namespace App\Support\Outbound;

interface DnsResolver
{
    /**
     * Resolve every address currently advertised for a host.
     *
     * @return array<int, string>
     */
    public function resolve(string $host): array;
}
