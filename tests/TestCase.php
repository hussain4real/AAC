<?php

namespace Tests;

use App\Support\Outbound\DnsResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\Outbound\FakeDnsResolver;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(DnsResolver::class, new FakeDnsResolver);
        config()->set('maacc.outbound.purposes.remote_http.allowed_hosts', ['*.example.com']);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
