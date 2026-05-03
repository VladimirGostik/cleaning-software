<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SupportedLanguage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'app' => fn () => [
                'name' => config('app.name'),
            ],

            'auth' => fn () => $this->authPayload($request->user()),

            'tenant' => fn () => $this->tenantPayload($request),

            'can' => fn () => $this->canPayload($request->user()),

            'flash' => fn () => [
                'success' => $request->session()->get('flash.success'),
                'error' => $request->session()->get('flash.error'),
                'info' => $request->session()->get('flash.info'),
                'status' => $request->session()->get('status'),
            ],

            'translations' => fn () => Arr::dot((array) trans('app')),

            'locale' => fn () => app()->getLocale(),

            'languages' => fn () => SupportedLanguage::options(),

            'canResetPassword' => fn () => Route::has('password.request'),
        ];
    }

    /**
     * @return array{user: array<string, mixed>|null}
     */
    private function authPayload(?User $user): array
    {
        if ($user === null) {
            return ['user' => null];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'is_active' => $user->is_active,
            ],
        ];
    }

    /**
     * @return array{active: array<string, mixed>|null, available: array<int, array<string, mixed>>}
     */
    private function tenantPayload(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['active' => null, 'available' => []];
        }

        $activeId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        $available = $user->tenants()
            ->wherePivot('is_active', true)
            ->get()
            ->map(function ($t) {
                /** @var Tenant $t */
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'is_active' => $t->is_active,
                ];
            })
            ->all();

        $active = collect($available)->firstWhere('id', $activeId);

        return ['active' => $active, 'available' => $available];
    }

    /**
     * @return array<string, bool>
     */
    private function canPayload(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $perms = [
            'viewClients', 'createClients', 'editClients', 'deleteClients',
            'viewObjects', 'createObjects', 'editObjects', 'deleteObjects',
            'viewQuotes', 'createQuotes', 'editQuotes',
            'viewContracts', 'createContracts',
            'viewEmployees', 'createEmployees',
            'viewSchedule', 'createSchedule',
            'viewInvoices', 'createInvoices',
            'viewTemplates',
            'manageRoles',
            'manageTenant',
            'viewAuditLogs',
        ];

        return collect($perms)
            ->mapWithKeys(fn (string $perm) => [
                $perm => $user->can($this->permissionString($perm)),
            ])
            ->all();
    }

    private function permissionString(string $camel): string
    {
        // viewClients → view clients ; manageBillingSettings → manage billing settings
        $snake = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $camel);

        return strtolower($snake);
    }
}
