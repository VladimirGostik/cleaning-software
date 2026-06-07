<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SubscriptionPlanEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin = Pro plan, owns the primary demo tenant
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'phone' => '+421 900 000 000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'sk',
                'is_active' => true,
                'subscription_plan' => SubscriptionPlanEnum::Pro->value,
            ],
        );

        // 4 demo-tier accounts, each owns exactly 1 tenant
        /** @var array<int, array{email: string, name: string, plan: SubscriptionPlanEnum, ico: string, tenant_name: string, is_vat_payer: bool, vat_number: string|null, contact_email: string}> $demoAccounts */
        $demoAccounts = [
            [
                'email' => 'free@demo.sk',
                'name' => 'Demo Free',
                'plan' => SubscriptionPlanEnum::Free,
                'ico' => '10000001',
                'tenant_name' => 'Demo Free s.r.o.',
                'is_vat_payer' => false,
                'vat_number' => null,
                'contact_email' => 'free@demo.sk',
            ],
            [
                'email' => 'starter@demo.sk',
                'name' => 'Demo Starter',
                'plan' => SubscriptionPlanEnum::Starter,
                'ico' => '10000002',
                'tenant_name' => 'Demo Starter s.r.o.',
                'is_vat_payer' => false,
                'vat_number' => null,
                'contact_email' => 'starter@demo.sk',
            ],
            [
                'email' => 'pro@demo.sk',
                'name' => 'Demo Pro',
                'plan' => SubscriptionPlanEnum::Pro,
                'ico' => '10000003',
                'tenant_name' => 'Demo Pro s.r.o.',
                'is_vat_payer' => false,
                'vat_number' => null,
                'contact_email' => 'pro@demo.sk',
            ],
            [
                'email' => 'enterprise@demo.sk',
                'name' => 'Demo Enterprise',
                'plan' => SubscriptionPlanEnum::Enterprise,
                'ico' => '10000004',
                'tenant_name' => 'Demo Enterprise s.r.o.',
                'is_vat_payer' => true,
                'vat_number' => 'SK9999999999',
                'contact_email' => 'enterprise@demo.sk',
            ],
        ];

        foreach ($demoAccounts as $account) {
            $demoUser = User::query()->firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'sk',
                    'is_active' => true,
                    'subscription_plan' => $account['plan']->value,
                ],
            );

            $tenant = Tenant::query()->firstOrCreate(
                ['ico' => $account['ico']],
                [
                    'owner_id' => $demoUser->id,
                    'name' => $account['tenant_name'],
                    'is_vat_payer' => $account['is_vat_payer'],
                    'vat_number' => $account['vat_number'],
                    'vat_rate' => 23,
                    'iban' => 'SK0000000000000000000000',
                    'address_line' => 'Hlavná 1',
                    'city' => 'Bratislava',
                    'postal_code' => '811 01',
                    'country' => 'SK',
                    'contact_email' => $account['contact_email'],
                    'contact_phone' => '+421 900 000 000',
                    'is_active' => true,
                ],
            );

            TenantMembership::query()->firstOrCreate(
                ['user_id' => $demoUser->id, 'tenant_id' => $tenant->id],
                ['is_active' => true, 'joined_at' => now()],
            );

            TenantInterface::query()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                ['color' => null],
            );

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            RoleTemplatesSeeder::seedForTenant($tenant);

            /** @var Role $ownerRole */
            $ownerRole = Role::where('name', 'Vlastník')
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $demoUser->assignRole($ownerRole);
        }

        // Admin's primary tenant (IČO 12345678, Pro plan)
        $adminTenant = Tenant::query()->firstOrCreate(
            ['ico' => '12345678'],
            [
                'owner_id' => $admin->id,
                'name' => 'Demo Cleaning s.r.o.',
                'is_vat_payer' => true,
                'vat_number' => 'SK1234567890',
                'vat_rate' => 23,
                'iban' => 'SK0000000000000000000000',
                'address_line' => 'Hlavná 1',
                'city' => 'Bratislava',
                'postal_code' => '811 01',
                'country' => 'SK',
                'contact_email' => 'admin@example.com',
                'contact_phone' => '+421 900 000 000',
                'is_active' => true,
            ],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $admin->id, 'tenant_id' => $adminTenant->id],
            ['is_active' => true, 'joined_at' => now()],
        );

        TenantInterface::query()->firstOrCreate(
            ['tenant_id' => $adminTenant->id],
            ['color' => null],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($adminTenant->id);
        RoleTemplatesSeeder::seedForTenant($adminTenant);

        /** @var Role $adminOwnerRole */
        $adminOwnerRole = Role::where('name', 'Vlastník')
            ->where('tenant_id', $adminTenant->id)
            ->firstOrFail();

        $admin->assignRole($adminOwnerRole);
    }
}
