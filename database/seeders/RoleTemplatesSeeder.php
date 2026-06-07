<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates default role templates per spec — one set per tenant.
 *
 * Vlastník        — all permissions
 * Vedúca         — operational team management
 * Upratovačka    — minimal field worker
 * Sekretárka     — admin support, no finance/employees
 * Účtovníčka     — finance only
 * Zákazník       — customer portal
 */
final class RoleTemplatesSeeder extends Seeder
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function templates(): array
    {
        return [
            'Vlastník' => [], // empty = all permissions assigned at runtime
            'Vedúca' => [
                PermissionEnum::ViewObjects->value,
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::CreateSchedule->value,
                PermissionEnum::EditSchedule->value,
                PermissionEnum::AssignCleaners->value,
                PermissionEnum::ViewEmployees->value,
                PermissionEnum::AssignEmployees->value,
                PermissionEnum::ViewComplaints->value,
                PermissionEnum::ResolveComplaints->value,
                PermissionEnum::RejectComplaints->value,
                PermissionEnum::ViewPhotos->value,
                PermissionEnum::ReviewPhotos->value,
            ],
            'Upratovačka' => [
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::ViewObjects->value,
            ],
            'Sekretárka' => [
                PermissionEnum::ViewClients->value,
                PermissionEnum::CreateClients->value,
                PermissionEnum::EditClients->value,
                PermissionEnum::DeleteClients->value,
                PermissionEnum::ViewObjects->value,
                PermissionEnum::CreateObjects->value,
                PermissionEnum::EditObjects->value,
                PermissionEnum::DeleteObjects->value,
                PermissionEnum::ViewQuotes->value,
                PermissionEnum::CreateQuotes->value,
                PermissionEnum::EditQuotes->value,
                PermissionEnum::SendQuotes->value,
                PermissionEnum::ViewContracts->value,
                PermissionEnum::CreateContracts->value,
                PermissionEnum::ViewTemplates->value,
                PermissionEnum::UploadTemplates->value,
                PermissionEnum::DeleteTemplates->value,
                PermissionEnum::ViewNotifications->value,
            ],
            'Účtovníčka' => [
                PermissionEnum::ViewInvoices->value,
                PermissionEnum::CreateInvoices->value,
                PermissionEnum::EditInvoices->value,
                PermissionEnum::CancelInvoices->value,
                PermissionEnum::ViewContracts->value,
                PermissionEnum::ViewClients->value,
                PermissionEnum::ViewObjects->value,
                PermissionEnum::ManageBillingSettings->value,
            ],
            'Zákazník' => [
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::ViewPhotos->value,
                PermissionEnum::ViewComplaints->value,
                PermissionEnum::ViewObjects->value,
            ],
        ];
    }

    public static function seedForTenant(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $allPermissions = Permission::all();

        foreach (self::templates() as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::findOrCreate($roleName, 'web');

            if ($roleName === 'Vlastník') {
                $role->syncPermissions($allPermissions);

                continue;
            }

            $role->syncPermissions($permissions);
        }
    }

    public function run(): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => self::seedForTenant($tenant));
    }
}
