<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Tenants\TenantListItemData;
use App\Enums\PermissionEnum;
use App\Enums\SupportedLanguage;
use App\Enums\TenantColorEnum;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Spatie\LaravelData\DataCollection;

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

            'tenantColors' => fn () => TenantColorEnum::options(),

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
     * @return array{active: TenantListItemData|null, available: DataCollection<int, TenantListItemData>}
     */
    private function tenantPayload(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return ['active' => null, 'available' => TenantListItemData::collect([], DataCollection::class)];
        }

        $activeId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        $tenants = $user->tenants()
            ->wherePivot('is_active', true)
            ->get();

        /** @var DataCollection<int, TenantListItemData> $available */
        $available = TenantListItemData::collect($tenants, DataCollection::class);

        $active = $tenants->first(fn (Tenant $t) => $t->id === $activeId);
        $activeDto = $active !== null ? TenantListItemData::fromModel($active) : null;

        return ['active' => $activeDto, 'available' => $available];
    }

    /**
     * @return array<string, bool>
     */
    private function canPayload(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        /** @var array<string, PermissionEnum> $map */
        $map = [
            'viewClients' => PermissionEnum::ViewClients,
            'createClients' => PermissionEnum::CreateClients,
            'editClients' => PermissionEnum::EditClients,
            'deleteClients' => PermissionEnum::DeleteClients,
            'viewObjects' => PermissionEnum::ViewObjects,
            'createObjects' => PermissionEnum::CreateObjects,
            'editObjects' => PermissionEnum::EditObjects,
            'deleteObjects' => PermissionEnum::DeleteObjects,
            'viewAllObjects' => PermissionEnum::ViewAllObjects,
            'viewQuotes' => PermissionEnum::ViewQuotes,
            'createQuotes' => PermissionEnum::CreateQuotes,
            'editQuotes' => PermissionEnum::EditQuotes,
            'viewContracts' => PermissionEnum::ViewContracts,
            'createContracts' => PermissionEnum::CreateContracts,
            'viewEmployees' => PermissionEnum::ViewEmployees,
            'createEmployees' => PermissionEnum::CreateEmployees,
            'viewSchedule' => PermissionEnum::ViewSchedule,
            'createSchedule' => PermissionEnum::CreateSchedule,
            'viewAllSchedule' => PermissionEnum::ViewAllSchedule,
            'viewInvoices' => PermissionEnum::ViewInvoices,
            'createInvoices' => PermissionEnum::CreateInvoices,
            'viewTemplates' => PermissionEnum::ViewTemplates,
            'manageRoles' => PermissionEnum::ManageRoles,
            'manageTenant' => PermissionEnum::ManageTenant,
            'viewAuditLogs' => PermissionEnum::ViewAuditLogs,
            'viewNotifications' => PermissionEnum::ViewNotifications,
        ];

        $result = [];
        foreach ($map as $key => $permission) {
            $result[$key] = $user->can($permission->value);
        }

        return $result;
    }
}
