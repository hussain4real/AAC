<?php

use App\Exceptions\OutboundRequestBlocked;
use App\Support\Outbound\OutboundRequestPolicy;
use Tests\Support\Outbound\FakeDnsResolver;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'maacc.outbound.require_https' => false,
        'maacc.outbound.purposes.remote_http.allowed_ports' => [443],
        'maacc.outbound.purposes.remote_http.allowed_hosts' => ['*.example.com'],
    ]);
});

function outboundPolicy(array $records = ['*' => ['1.1.1.1']]): OutboundRequestPolicy
{
    return new OutboundRequestPolicy(new FakeDnsResolver($records));
}

it('accepts an allowlisted public HTTPS destination and pins a resolved address', function () {
    $destination = outboundPolicy(['api.example.com' => ['2606:4700:4700::1111', '1.1.1.1']])
        ->inspect('https://api.example.com/v1/run', 'remote_http', ['api.example.com']);

    expect($destination->host)->toBe('api.example.com')
        ->and($destination->port)->toBe(443)
        ->and($destination->resolvedIps)->toBe(['1.1.1.1', '2606:4700:4700::1111'])
        ->and($destination->pinnedIp)->toBe('1.1.1.1')
        ->and($destination->guzzleOptions()['allow_redirects'])->toBeFalse()
        ->and($destination->guzzleOptions()['proxy'])->toBe('');
});

it('fails closed in production until infrastructure egress enforcement is attested', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['maacc.outbound.infrastructure_enforced' => false]);

    expect(fn () => outboundPolicy()->inspect('https://api.example.com/run', 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class, 'network egress controls have not been attested');
});

it('fails closed when a required destination allowlist is empty', function () {
    config(['maacc.outbound.purposes.remote_http.allowed_hosts' => []]);

    expect(fn () => outboundPolicy()->inspect('https://api.example.com/run', 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class, 'no destination hosts are approved');
});

it('rejects private reserved metadata and special-use IPv4 destinations', function (string $url) {
    expect(fn () => outboundPolicy()->inspect($url, 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class);
})->with([
    'unspecified' => 'https://0.0.0.0/run',
    'loopback' => 'https://127.0.0.1/run',
    'private class a' => 'https://10.0.0.1/run',
    'private class b' => 'https://172.16.0.1/run',
    'private class c' => 'https://192.168.1.1/run',
    'carrier grade nat' => 'https://100.64.0.1/run',
    'link local metadata' => 'https://169.254.169.254/latest/meta-data',
    'benchmark' => 'https://198.18.0.1/run',
    'documentation' => 'https://203.0.113.10/run',
    'multicast' => 'https://224.0.0.1/run',
]);

it('rejects private reserved and mapped IPv6 destinations', function (string $url) {
    expect(fn () => outboundPolicy()->inspect($url, 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class);
})->with([
    'unspecified' => 'https://[::]/run',
    'loopback' => 'https://[::1]/run',
    'mapped loopback' => 'https://[::ffff:127.0.0.1]/run',
    'unique local' => 'https://[fd00::1]/run',
    'link local' => 'https://[fe80::1]/run',
    'documentation' => 'https://[2001:db8::1]/run',
    'multicast' => 'https://[ff02::1]/run',
]);

it('rejects alternate numeric address encodings', function (string $url) {
    expect(fn () => outboundPolicy()->inspect($url, 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class, 'alternate numeric address encodings');
})->with([
    'single decimal' => 'https://2130706433/run',
    'short dotted decimal' => 'https://127.1/run',
    'octal' => 'https://0177.0.0.1/run',
    'hexadecimal' => 'https://0x7f.0x0.0x0.0x1/run',
]);

it('rejects URL userinfo trailing dots unapproved ports and non-HTTPS destinations', function (string $url) {
    config(['maacc.outbound.require_https' => true]);

    expect(fn () => outboundPolicy()->inspect($url, 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class);
})->with([
    'userinfo' => 'https://user:secret@api.example.com/run',
    'trailing dot' => 'https://api.example.com./run',
    'unapproved port' => 'https://api.example.com:8443/run',
    'plain HTTP' => 'http://api.example.com/run',
    'unsupported scheme' => 'ftp://api.example.com/run',
]);

it('rejects metadata names unresolved hosts and hosts outside the allowlist', function () {
    $policy = outboundPolicy([
        'unresolved.example.com' => [],
        'api.example.com' => ['1.1.1.1'],
    ]);

    expect(fn () => $policy->inspect('https://metadata.google.internal/keys', 'sso'))
        ->toThrow(OutboundRequestBlocked::class)
        ->and(fn () => $policy->inspect('https://unresolved.example.com/run', 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class)
        ->and(fn () => $policy->inspect('https://api.example.com/run', 'remote_http', ['trusted.example.com']))
        ->toThrow(OutboundRequestBlocked::class);
});

it('rejects a hostname when any resolved address is non-public', function () {
    $policy = outboundPolicy(['mixed.example.com' => ['1.1.1.1', '10.0.0.5']]);

    expect(fn () => $policy->inspect('https://mixed.example.com/run', 'remote_http'))
        ->toThrow(OutboundRequestBlocked::class, 'resolves to a non-public address');
});

it('supports exact and subdomain wildcard allowlists without matching the apex', function () {
    $policy = outboundPolicy();

    expect($policy->inspect('https://api.example.com/run', 'remote_http', ['*.example.com'])->host)
        ->toBe('api.example.com')
        ->and(fn () => $policy->inspect('https://example.com/run', 'remote_http', ['*.example.com']))
        ->toThrow(OutboundRequestBlocked::class);
});
