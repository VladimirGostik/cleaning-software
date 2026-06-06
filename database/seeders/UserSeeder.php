<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SubscriptionPlanEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'phone' => '+421 900 000 000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'sk',
                'is_active' => true,
            ],
        );

        $tenant = Tenant::query()->firstOrCreate(
            ['ico' => '12345678'],
            [
                'name' => 'Demo Cleaning s.r.o.',
                'is_vat_payer' => true,
                'vat_number' => 'SK1234567890',
                'vat_rate' => 23,
                'iban' => 'SK0000000000000000000000',
                'address_line' => 'Hlavná 1',
                'city' => 'Bratislava',
                'postal_code' => '811 01',
                'country' => 'SK',
                'contact_email' => 'kontakt@cleanmaster.test',
                'contact_phone' => '+421 900 000 000',
                'subscription_plan' => SubscriptionPlanEnum::Pro->value,
                'is_active' => true,
            ],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $admin->id, 'tenant_id' => $tenant->id],
            ['is_active' => true, 'joined_at' => now()],
        );

        // Create role templates for this tenant, then assign Vlastník to admin
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->callOnce(RoleTemplatesSeeder::class);

        $ownerRole = Role::where('name', 'Vlastník')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $admin->assignRole($ownerRole);
    }
}
