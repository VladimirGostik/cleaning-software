<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ChecksFeatures;
use App\Services\ConfigFeatureChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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

        RateLimiter::for('password-reset-confirm', function (Request $r): Limit {
            return Limit::perMinute(10)->by(get_client_ip());
        });
    }
}
