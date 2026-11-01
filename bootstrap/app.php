<?php

declare(strict_types=1);

use App\Http\Middleware\ApiLocaleMiddleware;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RequireActiveTenant;
use App\Http\Middleware\TenantContextMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'tenant.context' => TenantContextMiddleware::class,
            'tenant.required' => RequireActiveTenant::class,
            'ability' => CheckForAnyAbility::class,
            'locale.api' => ApiLocaleMiddleware::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: TenantContextMiddleware::class);

        $middleware->web(append: [
            LocaleMiddleware::class,
            TenantContextMiddleware::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
