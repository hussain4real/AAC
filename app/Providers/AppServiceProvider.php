<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Secrets\Contracts\SecretVault;
use App\Support\Secrets\DatabaseSecretVault;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        // Bind the platform secrets vault. The database-backed driver is the
        // default; an enterprise deployment swaps in an external vault (HashiCorp
        // Vault, AWS Secrets Manager, …) via `maac.vault.driver` with no caller
        // change, since every consumer depends on the SecretVault interface.
        $this->app->bind(SecretVault::class, fn (Application $app): SecretVault => $app->make(
            (string) config('maac.vault.driver', DatabaseSecretVault::class),
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
    }

    /**
     * Grant the MAAC Super Admin platform role an unrestricted authorization
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
