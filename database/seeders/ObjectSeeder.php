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
                ->withContacts(random_int(1, 2))
                ->create(['client_id' => $client->id]);

            if ($client->id === $lastClientId) {
                // Deactivated object keeps a contact — demo proof that `is_active` and
                // `deleted_at` (or contact ownership) are orthogonal.
                CleaningObject::factory()->inactive()->withContacts()->create(['client_id' => $client->id]);
            }
        }
    }
}
