<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression tests for two BE bugs fixed after initial implementation:
 *
 * Bug A — create() and edit() were missing the `objects` prop.
 * Bug B — `customer_representative` column not round-tripping through create, show,
 *          cancel (credit note copy), and duplicate.
 */
final class InvoiceRegressionTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Bug A — `objects` prop present on create and edit
    // =========================================================================

    public function test_create_page_returns_objects_prop_with_id_name_client_id(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('objects', 2)
            ->has('objects.0', fn (Assert $item) => $item
                ->has('id')
                ->has('name')
                ->has('client_id')
                ->etc(),
            ),
        );
    }

    public function test_create_page_objects_prop_is_tenant_scoped(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // 1 object in current tenant
        $ownClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $ownClient->id,
            'name' => 'OwnObject',
        ]);

        // 1 object in a different tenant — must NOT appear
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        CleaningObject::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'name' => 'ForeignObject',
        ]);

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert — only 1 object, own tenant's
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('objects', 1)
            ->where('objects.0.name', 'OwnObject'),
        );
    }

    public function test_edit_page_returns_objects_prop_with_id_name_client_id(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.edit', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Edit')
            ->has('objects', 2)
            ->has('objects.0', fn (Assert $item) => $item
                ->has('id')
                ->has('name')
                ->has('client_id')
                ->etc(),
            ),
        );
    }

    public function test_edit_page_objects_prop_excludes_other_tenant_objects(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $ownClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $ownClient->id,
            'name' => 'OwnObject',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        CleaningObject::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
            'name' => 'ForeignObject',
        ]);

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->get(route('invoices.edit', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Edit')
            ->has('objects', 1)
            ->where('objects.0.name', 'OwnObject'),
        );
    }

    public function test_create_page_objects_prop_is_empty_when_no_objects_exist(): void
    {
        // Arrange — tenant has no objects
        $user = $this->actingAsTenantUser('Admin');

        // Act
        $response = $this->get(route('invoices.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('objects', 0),
        );
    }

    // =========================================================================
    // Bug B — `customer_representative` round-trips
    // =========================================================================

    public function test_customer_representative_is_persisted_on_store(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Novák s.r.o.',
            'customer_representative' => 'Ing. Novák',
            'items' => [
                ['description' => 'Upratovanie', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        // Act
        $response = $this->post(route('invoices.store'), $payload);

        // Assert — DB row
        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'customer_name' => 'Novák s.r.o.',
            'customer_representative' => 'Ing. Novák',
        ]);
    }

    public function test_customer_representative_appears_in_show_inertia_prop(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_representative' => 'Ing. Novák',
        ]);

        // Act
        $response = $this->get(route('invoices.show', $invoice));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('invoice.customer_representative', 'Ing. Novák'),
        );
    }

    public function test_customer_representative_null_is_allowed_and_returned_as_null(): void
    {
        // Edge — field is nullable; absence must not explode serialisation
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_representative' => null,
        ]);

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('invoice.customer_representative', null),
        );
    }

    public function test_customer_representative_is_copied_into_credit_note_on_cancel(): void
    {
        // Arrange — issued invoice with representative
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Novák s.r.o.',
            'customer_representative' => 'Ing. Novák',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
        ]);

        // Act
        $response = $this->post(route('invoices.cancel', $invoice));

        // Assert — credit note carries the representative
        $response->assertRedirect();

        $creditNote = Invoice::withoutGlobalScopes()
            ->where('credited_invoice_id', $invoice->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->assertSame('Ing. Novák', $creditNote->customer_representative);
    }

    public function test_customer_representative_is_copied_on_duplicate(): void
    {
        // Arrange — draft invoice with representative
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Novák s.r.o.',
            'customer_representative' => 'Ing. Novák',
        ]);

        // Act
        $response = $this->post(route('invoices.duplicate', $invoice));

        // Assert — redirects to edit of the new draft; new invoice carries representative
        $response->assertRedirect();

        // Fetch the new draft (not the original, not soft-deleted)
        $duplicate = Invoice::where('tenant_id', $tenant->id)
            ->where('id', '!=', $invoice->id)
            ->where('status', InvoiceStatusEnum::Draft->value)
            ->firstOrFail();

        $this->assertSame('Ing. Novák', $duplicate->customer_representative);
    }
}
