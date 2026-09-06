<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceFormContextTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAdmin(): Tenant
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $tenant);

        return $tenant;
    }

    public function test_create_objects_context_only_includes_active_objects(): void
    {
        $tenant = $this->tenantAdmin();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Create', shouldExist: false)
                ->has('context.objects', 1),
        );
    }

    public function test_edit_objects_context_includes_invoices_own_inactive_object(): void
    {
        $tenant = $this->tenantAdmin();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $activeObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $inactiveObject = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $invoice = Invoice::factory()->forClient($client)->forObject($inactiveObject)->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $inactiveObject->id,
        ]);

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Edit', shouldExist: false)
                ->has('context.objects', 2),
        );

        unset($activeObject);
    }

    public function test_form_context_is_tenant_scoped(): void
    {
        $tenant = $this->tenantAdmin();
        $other = Tenant::factory()->create();
        Client::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Client::factory()->count(3)->create(['tenant_id' => $other->id]);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Create', shouldExist: false)
                ->has('context.clients', 2),
        );
    }
}
