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
        $tenant = Tenant::where('ico', '12345678')->firstOrFail();
        app()->instance('current_tenant_id', $tenant->id);

        Client::factory()->count(6)->withContacts(2)->create([
            'tenant_id' => $tenant->id,
        ]);

        Client::factory()->count(3)->private()->withContacts(1)->create([
            'tenant_id' => $tenant->id,
        ]);
    }
}
