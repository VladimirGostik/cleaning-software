<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Activity;
use App\Models\Media;
use App\Policies\ActivityPolicy;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Activity::class => ActivityPolicy::class,
        Media::class => MediaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
