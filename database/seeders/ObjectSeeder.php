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
        // Objects are feature-gated to Starter+ plan (objects feature).
        // Seeding bypasses the gate intentionally — raw rows for demo data.
        // Free tenant (IČO 10000001) is skipped: owner's plan doesn't unlock Objects,
        // so seeded rows would never be visible in UI — keeping it clean.

        // Admin's Pro tenant — richest data set
        $adminTenant = Tenant::where('ico', '12345678')->firstOrFail();
        $this->seedObjectsForTenant($adminTenant, inactiveOnLast: true);

        // Starter, Pro, Enterprise tenants — Objects feature unlocked
        $eligibleIcos = ['10000002', '10000003', '10000004'];

        foreach ($eligibleIcos as $ico) {
            $tenant = Tenant::where('ico', $ico)->first();

            if ($tenant === null) {
                continue;
            }

            $this->seedObjectsForTenant($tenant);
        }
    }

    private function seedObjectsForTenant(Tenant $tenant, bool $inactiveOnLast = false): void
    {
        app()->instance('current_tenant_id', $tenant->id);

        $clients = Client::query()->get();

        foreach ($clients as $index => $client) {
            $count = fake()->numberBetween(1, 3);
            $isLast = $inactiveOnLast && $index === $clients->count() - 1;

            if ($isLast) {
                // Seed one inactive object on the last client for demo variety
                CleaningObject::factory()->inactive()->create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                ]);

                $count--;
            }

            if ($count > 0) {
                CleaningObject::factory()->count($count)->create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                ]);
            }
        }
    }
}
