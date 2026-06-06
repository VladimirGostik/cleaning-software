<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ChecksFeatures;
use App\Models\User;
use App\Services\ConfigFeatureChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChecksFeatures::class, ConfigFeatureChecker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        /**
         * Platform super-admin bypass (OPTION A — boolean column, not Spatie role).
         *
         * Returns true  → all Gate/Policy checks pass for the user.
         * Returns null  → normal users fall through to policies and Spatie permission checks.
         * Never returns false — that would short-circuit and DENY without running policies.
         *
         * Scope of bypass: applies to both $user->can(...) (permission axis) AND Policy methods.
         * Does NOT bypass feature: middleware (plan axis) — super-admin is a person-level override,
         * not a plan entitlement grant.
         */
        Gate::before(function (User $user): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });

        RateLimiter::for('api', function (Request $r): Limit {
            return Limit::perMinute(60)
                ->by($r->user()?->id ?: get_client_ip());
        });

        RateLimiter::for('login', function (Request $r): array {
            return [
                Limit::perMinute(500),
                Limit::perMinute(5)->by('email:' . $r->input('email')),
            ];
        });

        RateLimiter::for('password-reset', function (Request $r): array {
            return [
                Limit::perMinute(3)->by('email:' . $r->input('email')),
                Limit::perMinute(10)->by('ip:' . get_client_ip()),
            ];
        });

        RateLimiter::for('password-reset-confirm', function (): Limit {
            return Limit::perMinute(10)->by(get_client_ip());
        });
    }
}
