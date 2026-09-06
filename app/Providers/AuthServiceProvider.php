<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Media;
use App\Policies\ActivityPolicy;
use App\Policies\MediaPolicy;
use App\Policies\ObjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Activity::class => ActivityPolicy::class,
        Media::class => MediaPolicy::class,
        // `ClientPolicy` is auto-discovered (`Client` -> `ClientPolicy`); `CleaningObject` needs
        // an explicit mapping since the model name does not match the policy's `Object` stem.
        CleaningObject::class => ObjectPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
