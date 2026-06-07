<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

final class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // Admin's Pro tenant — primary data set
        $adminTenant = Tenant::where('ico', '12345678')->firstOrFail();
        app()->instance('current_tenant_id', $adminTenant->id);

        Client::factory()->count(6)->withContacts(2)->create([
            'tenant_id' => $adminTenant->id,
        ]);

        Client::factory()->count(3)->private()->withContacts(1)->create([
            'tenant_id' => $adminTenant->id,
        ]);

        // Demo-tier tenants — small client set per tenant so every login lands on data
        $demoIcos = ['10000001', '10000002', '10000003', '10000004'];

        foreach ($demoIcos as $ico) {
            $tenant = Tenant::where('ico', $ico)->first();

            if ($tenant === null) {
                continue;
            }

            app()->instance('current_tenant_id', $tenant->id);

            Client::factory()->count(3)->withContacts(1)->create([
                'tenant_id' => $tenant->id,
            ]);

            Client::factory()->count(1)->private()->withContacts(1)->create([
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
