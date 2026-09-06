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
                ->component('Invoices/Create')
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
                ->component('Invoices/Edit')
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
                ->component('Invoices/Create')
                ->has('context.clients', 2),
        );
    }

    // -------------------------------------------------------------------------
    // supplier_missing_fields exposure
    // -------------------------------------------------------------------------

    public function test_create_context_exposes_missing_supplier_fields_for_incomplete_tenant(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create(['address_line' => null]);
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Create')
                ->where('context.supplier_missing_fields', ['address_line']),
        );
    }

    public function test_create_context_supplier_missing_fields_is_empty_for_complete_tenant(): void
    {
        $tenant = $this->tenantAdmin();

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Create')
                ->where('context.supplier_missing_fields', []),
        );
    }

    public function test_show_draft_invoice_exposes_missing_supplier_fields(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create(['address_line' => null]);
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Show')
                ->where('invoice.supplier_missing_fields', ['address_line']),
        );
    }

    public function test_show_issued_invoice_hides_missing_supplier_fields_even_if_tenant_incomplete(): void
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $tenant->update(['address_line' => null]);

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Invoices/Show')
                ->where('invoice.supplier_missing_fields', []),
        );
    }
}
