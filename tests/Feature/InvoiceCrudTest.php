<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_authenticated_user_with_view_permission_can_list_invoices(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Test Corp',
        ]);

        $response = $this->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 3),
        );
    }

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $response = $this->get(route('invoices.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_view_invoices_permission_gets_403(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.index'));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create — prop names (BUG 1)
    // -------------------------------------------------------------------------

    public function test_create_page_renders_correct_prop_keys(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('clients')
            ->has('typeOptions')
            ->has('templateOptions')
            ->has('statusOptions')
            ->has('isVatPayer')
            ->where('isVatPayer', fn ($v) => is_bool($v)),
        );
    }

    // -------------------------------------------------------------------------
    // edit — prop names (BUG 1)
    // -------------------------------------------------------------------------

    public function test_edit_page_renders_correct_prop_keys(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Edit')
            ->has('invoice')
            ->has('clients')
            ->has('typeOptions')
            ->has('templateOptions')
            ->has('statusOptions')
            ->has('isVatPayer')
            ->where('isVatPayer', fn ($v) => is_bool($v)),
        );
    }

    // -------------------------------------------------------------------------
    // create / store
    // -------------------------------------------------------------------------

    public function test_user_can_create_standalone_invoice(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Standalone Customer',
            'items' => [
                [
                    'description' => 'Upratovanie',
                    'quantity' => 2.0,
                    'unit_price' => 50.0,
                ],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'customer_name' => 'Standalone Customer',
            'status' => InvoiceStatusEnum::Draft->value,
        ]);
    }

    public function test_create_invoice_for_client_populates_snapshot(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Client s.r.o.',
            'ico' => '12345678',
            'city' => 'Bratislava',
        ]);

        $payload = [
            'client_id' => $client->id,
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'customer_name' => 'Test Client s.r.o.',
            'customer_ico' => '12345678',
            'customer_city' => 'Bratislava',
        ]);
    }

    public function test_create_invoice_for_object_populates_object_snapshot(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'name' => 'Objekt A',
            'city' => 'Košice',
        ]);

        $payload = [
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Cleaning', 'quantity' => 1.0, 'unit_price' => 80.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'cleaning_object_id' => $object->id,
            'object_name' => 'Objekt A',
            'object_city' => 'Košice',
        ]);
    }

    public function test_store_fails_when_standalone_without_customer_name(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            // no client_id, no customer_name
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors('customer_name');
    }

    public function test_store_fails_when_object_belongs_to_different_client(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $objectOfB = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $clientB->id,
        ]);

        $payload = [
            'client_id' => $clientA->id,
            'cleaning_object_id' => $objectOfB->id,
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors('cleaning_object_id');
    }

    public function test_store_fails_with_cross_tenant_client_id(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $foreignClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);

        $payload = [
            'client_id' => $foreignClient->id,
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_monthly_invoice_requires_period_from_and_to(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $payload = [
            'type' => InvoiceTypeEnum::Monthly->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Test',
            // missing period_from, period_to
            'items' => [
                ['description' => 'Cleaning', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors(['period_from', 'period_to']);
    }

    public function test_non_vat_payer_tenant_creates_invoice_with_zero_vat_amount(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['is_vat_payer' => false]);

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Test',
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $this->post(route('invoices.store'), $payload);

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $tenant->id,
            'is_vat_payer' => false,
            'vat_amount' => '0.00',
        ]);

        $invoice = Invoice::where('tenant_id', $tenant->id)->first();
        $this->assertNull($invoice->vat_rate);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_user_can_update_draft_invoice(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        // Use non-VAT payer for predictable total
        $tenant->update(['is_vat_payer' => false]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Original Name',
        ]);

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Updated Name',
            'items' => [
                ['description' => 'Updated Service', 'quantity' => 3.0, 'unit_price' => 40.0],
            ],
        ];

        $response = $this->put(route('invoices.update', $invoice), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'customer_name' => 'Updated Name',
            'total' => '120.00',
        ]);
    }

    public function test_update_issued_invoice_is_rejected(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $payload = [
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => 'Attempt',
            'items' => [
                ['description' => 'Service', 'quantity' => 1.0, 'unit_price' => 100.0],
            ],
        ];

        $response = $this->put(route('invoices.update', $invoice), $payload);

        // ValidationException from service → redirect back with session errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_user_can_view_invoice(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Invoices/Show'));
    }

    public function test_cross_tenant_invoice_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $foreignInvoice = Invoice::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->get(route('invoices.show', $foreignInvoice));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_draft_invoice(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('invoices.destroy', $invoice));

        $response->assertRedirect(route('invoices.index'));
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_user_cannot_delete_issued_invoice(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('invoices.destroy', $invoice));

        // ValidationException → redirect back with errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }

    // -------------------------------------------------------------------------
    // feature gate
    // -------------------------------------------------------------------------

    public function test_free_plan_user_gets_403_on_invoices_route(): void
    {
        // User factory defaults to Free plan — no setUserPlan call
        $this->actingAsTenantUser('Vlastník');

        $response = $this->get(route('invoices.index'));

        $response->assertForbidden();
    }
}
