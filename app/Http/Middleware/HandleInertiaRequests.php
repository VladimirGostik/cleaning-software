<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Tenants\TenantListItemData;
use App\Enums\PermissionEnum;
use App\Enums\SupportedLanguage;
use App\Enums\TenantColorEnum;
use App\Navigation\NavigationRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
                    'locale' => $request->user()->locale,
                ] : null,
            ],
            'tenant' => function () use ($request): array {
                $user = $request->user();

                if ($user === null) {
                    return ['active' => null, 'available' => []];
                }

                $tenants = $user->tenants()
                    ->wherePivot('is_active', true)
                    ->with('interface')
                    ->orderBy('name')
                    ->get();

                $activeTenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
                $active = $tenants->firstWhere('id', $activeTenantId);

                return [
                    'active' => $active !== null ? TenantListItemData::fromModel($active) : null,
                    'available' => $tenants->map(fn ($t) => TenantListItemData::fromModel($t))->values()->toArray(),
                ];
            },
            'tenantColors' => fn () => TenantColorEnum::options(),
            'can' => function () use ($request): array {
                $u = $request->user();
                if ($u === null) {
                    return [];
                }

                return collect(PermissionEnum::cases())
                    ->mapWithKeys(fn (PermissionEnum $p) => [$p->sharedKey() => $u->can($p->value)])
                    ->toArray();
            },
            'locale' => fn () => app()->getLocale(),
            'languages' => fn () => SupportedLanguage::getForLanguageSwitcher(),
            'navigation' => fn () => $this->navigation->forUser($request->user()),
        ];
    }
}
