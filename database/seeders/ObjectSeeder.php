<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

final class ObjectSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('ico', '12345678')->first();

        if ($tenant === null) {
            return;
        }

        app()->instance('current_tenant_id', $tenant->id);

        $clients = Client::all();
        $lastClientId = $clients->last()?->id;

        foreach ($clients as $client) {
            CleaningObject::factory()
                ->count(random_int(1, 3))
                ->create(['client_id' => $client->id]);

            if ($client->id === $lastClientId) {
                CleaningObject::factory()->inactive()->create(['client_id' => $client->id]);
            }
        }
    }
}
