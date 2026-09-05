<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        // Activity lives in vendor (Spatie\Activitylog\Models), outside App\Models — auto-discovery skips it.
        Activity::class => ActivityPolicy::class,
        // Media lives in vendor (Spatie\MediaLibrary\MediaCollections\Models), outside App\Models — auto-discovery skips it.
        Media::class => MediaPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
