<?php

namespace App\Providers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\Governance\Contracts\AuditArchive;
use App\Support\MaaccConsoleCache;
use App\Support\Outbound\DnsResolver;
use App\Support\Outbound\SystemDnsResolver;
use App\Support\ProductReadinessProbe;
use App\Support\Sdk\SdkContext;
use App\Support\Secrets\Contracts\SecretVault;
use App\Support\Secrets\DatabaseSecretVault;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuditArchive::class, fn (Application $app): AuditArchive => $app->make(
            (string) config('maacc.audit.archive_driver'),
        ));

        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);

        // Bind the platform secrets vault. The database-backed driver is the
        // default; an enterprise deployment swaps in an external vault (HashiCorp
        // Vault, AWS Secrets Manager, …) via `maacc.vault.driver` with no caller
        // change, since every consumer depends on the SecretVault interface.
        $this->app->bind(SecretVault::class, fn (Application $app): SecretVault => $app->make(
            (string) config('maacc.vault.driver', DatabaseSecretVault::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
        $this->configurePlatformAuthorization();
        $this->configureConsoleCacheInvalidation();
        $this->configureSdkRateLimits();

        Event::listen(
            DiagnosingHealth::class,
            function (): void {
                app(ProductReadinessProbe::class)->assertReady();
            },
        );
    }

    /**
     * Apply route-weighted, application-scoped API limits. Expensive run starts
     * and callbacks receive smaller per-minute budgets than read-only discovery.
     */
    protected function configureSdkRateLimits(): void
    {
        RateLimiter::for('maacc-sdk', function (Request $request): Limit {
            $context = $request->attributes->get('sdk_context');
            $application = $context instanceof SdkContext ? $context->application->id : $request->ip();
            $path = $request->path();
            $weight = match (true) {
                $request->isMethod('POST') && str_ends_with($path, '/runs') => 10,
                $request->isMethod('POST') && str_ends_with($path, '/tool-results') => 5,
                str_ends_with($path, '/stream') => 4,
                $request->isMethod('POST') => 2,
                default => 1,
            };
            $budget = max(1, intdiv((int) config('maacc.runtime.api_weight_budget_per_minute', 120), $weight));

            return Limit::perMinute($budget)->by($application.'|'.$weight);
        });
    }

    /**
     * Invalidate only the changed tenant's versioned console aggregate cache.
     * Authentication models are ignored so routine login writes do not evict
     * unrelated reporting snapshots.
     */
    protected function configureConsoleCacheInvalidation(): void
    {
        $ignored = [User::class, Team::class, TeamInvitation::class];

        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], function (string $event, array $models) use ($ignored): void {
            $model = $models[0] ?? null;

            if (! $model instanceof Model || ! str_starts_with($model::class, 'App\\Models\\')) {
                return;
            }

            if (in_array($model::class, $ignored, true)) {
                return;
            }

            app(MaaccConsoleCache::class)->invalidateForModel($model);
        });
    }

    /**
     * Grant the MAACC Super Admin platform role an unrestricted authorization
     * override (Phase 8B). Returning null falls through to the normal policy and
     * permission checks, so only a Super Admin is short-circuited — every other
     * platform role is gated by its explicit permissions.
     */
    protected function configurePlatformAuthorization(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => $user->isPlatformSuperAdmin() ? true : null);
    }

    /**
     * Configure Passport for short-lived SDK/runtime access tokens. Applications
     * exchange their credential (client id/secret) for client_credentials tokens
     * at `/oauth/token`; these are intentionally short-lived.
     */
    protected function configurePassport(): void
    {
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(7));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );

        Model::automaticallyEagerLoadRelationships();

        Model::preventLazyLoading(! app()->isProduction());
    }
}
