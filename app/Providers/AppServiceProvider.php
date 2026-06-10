<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ChecksFeatures;
use App\Models\CleaningObject;
use App\Policies\ObjectPolicy;
use App\Services\ConfigFeatureChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChecksFeatures::class, ConfigFeatureChecker::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        // CleaningObject uses a non-standard class/policy name pair — explicit registration
        // required because auto-discovery expects App\Policies\CleaningObjectPolicy, not ObjectPolicy.
        Gate::policy(CleaningObject::class, ObjectPolicy::class);

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

        RateLimiter::for('register', function (Request $r): array {
            return [
                Limit::perMinute(3)->by('ip:' . get_client_ip()),
                Limit::perMinute(3)->by('email:' . $r->input('email')),
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

        RateLimiter::for('ico-lookup', function (): Limit {
            return Limit::perMinute(30)->by('ip:' . get_client_ip());
        });

        RateLimiter::for('invitation-accept', function (Request $r): Limit {
            return Limit::perMinute(5)->by('ip:' . get_client_ip());
        });
    }
}
