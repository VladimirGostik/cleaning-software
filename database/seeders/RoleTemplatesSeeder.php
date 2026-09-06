<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates default role templates per spec — one set per tenant.
 *
 * Admin               — all permissions
 * Vedúca              — operational team management, full read visibility
 * Interná upratovačka — minimal field worker, own-only visibility
 * Sekretárka          — admin support, no finance/employees, full object visibility
 * Účtovníčka          — finance only, full object visibility
 * Zákazník            — customer portal, own-only visibility
 */
final class RoleTemplatesSeeder extends Seeder
{
    public const string ADMIN_ROLE = 'Admin';

    /** @return array<string, list<string>> */
    public static function templates(): array
    {
        return [
            self::ADMIN_ROLE => [], // empty = all permissions assigned at runtime
            'Vedúca' => [
                PermissionEnum::ViewQuotes->value,
                PermissionEnum::ViewObjects->value,
                PermissionEnum::ViewAllObjects->value,
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::ViewAllSchedule->value,
                PermissionEnum::CreateSchedule->value,
                PermissionEnum::EditSchedule->value,
                PermissionEnum::AssignCleaners->value,
                PermissionEnum::ViewEmployees->value,
                PermissionEnum::AssignEmployees->value,
                PermissionEnum::ViewNotifications->value,
            ],
            'Interná upratovačka' => [
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::ViewObjects->value,
            ],
            'Sekretárka' => [
                PermissionEnum::ViewClients->value,
                PermissionEnum::CreateClients->value,
                PermissionEnum::EditClients->value,
                PermissionEnum::DeleteClients->value,
                PermissionEnum::ViewObjects->value,
                PermissionEnum::ViewAllObjects->value,
                PermissionEnum::CreateObjects->value,
                PermissionEnum::EditObjects->value,
                PermissionEnum::DeleteObjects->value,
                PermissionEnum::ViewQuotes->value,
                PermissionEnum::CreateQuotes->value,
                PermissionEnum::EditQuotes->value,
                PermissionEnum::SendQuotes->value,
                PermissionEnum::ApproveQuotes->value,
                PermissionEnum::DeleteQuotes->value,
                PermissionEnum::ViewContracts->value,
                PermissionEnum::CreateContracts->value,
                PermissionEnum::DeleteContracts->value,
                PermissionEnum::ViewContractTemplates->value,
                PermissionEnum::CreateContractTemplates->value,
                PermissionEnum::EditContractTemplates->value,
                PermissionEnum::DeleteContractTemplates->value,
                PermissionEnum::UploadFiles->value,
                PermissionEnum::ViewMedia->value,
                PermissionEnum::ViewNotifications->value,
            ],
            'Účtovníčka' => [
                PermissionEnum::ViewQuotes->value,
                PermissionEnum::ViewInvoices->value,
                PermissionEnum::CreateInvoices->value,
                PermissionEnum::EditInvoices->value,
                PermissionEnum::CancelInvoices->value,
                PermissionEnum::ViewRecurringInvoices->value,
                PermissionEnum::CreateRecurringInvoices->value,
                PermissionEnum::EditRecurringInvoices->value,
                PermissionEnum::DeleteRecurringInvoices->value,
                PermissionEnum::ViewContracts->value,
                PermissionEnum::ViewContractTemplates->value,
                PermissionEnum::ViewClients->value,
                PermissionEnum::ViewObjects->value,
                PermissionEnum::ViewAllObjects->value,
                PermissionEnum::ManageBillingSettings->value,
                PermissionEnum::ViewNotifications->value,
            ],
            'Zákazník' => [
                PermissionEnum::ViewSchedule->value,
                PermissionEnum::ViewObjects->value,
            ],
        ];
    }

    public static function seedForTenant(Tenant $tenant): void
    {
        // Guards `app:create-owner` running on a freshly migrated database (no prior
        // `db:seed`) — the permission catalogue must exist before roles can reference it.
        (new PermissionSeeder)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $allPermissions = Permission::all();

        foreach (self::templates() as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::findOrCreate($roleName, 'web');

            if ($roleName === self::ADMIN_ROLE) {
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
