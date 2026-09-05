<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLanguage;
use App\Navigation\NavigationRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class HandleInertiaRequests extends Middleware
{
    public function __construct(private readonly NavigationRegistry $navigation) {}

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'auth' => fn () => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'can' => function () use ($request): array {
                $u = $request->user();
                if ($u === null) {
                    return [];
                }

                return [
                    'viewUsers' => $u->can('view users'),
                    'createUsers' => $u->can('create users'),
                    'editUsers' => $u->can('edit users'),
                    'deleteUsers' => $u->can('delete users'),
                    'viewRoles' => $u->can('view roles'),
                    'createRoles' => $u->can('create roles'),
                    'editRoles' => $u->can('edit roles'),
                    'deleteRoles' => $u->can('delete roles'),
                    'viewAuditLogs' => $u->can('viewAny', Activity::class),
                    'viewMedia' => $u->can('viewAny', Media::class),
                ];
            },
            'locale' => fn () => app()->getLocale(),
            'languages' => fn () => SupportedLanguage::getForLanguageSwitcher(),
            'navigation' => fn () => $this->navigation->forUser($request->user()),
        ];
    }
}
