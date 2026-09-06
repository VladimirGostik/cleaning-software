<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, InvoiceItemData> */
    private function oneItem(): array
    {
        return [new InvoiceItemData(
            id: null,
            description: 'Cleaning service',
            quantity: 2,
            unit: 'hod',
            unit_price: 30,
            discount_percent: 0,
            vat_rate: 23,
        )];
    }

    /** @param  array<string, mixed>  $overrides */
    private function upsertData(array $overrides = []): InvoiceUpsertData
    {
        return InvoiceUpsertData::from(array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'type' => InvoiceTypeEnum::OneOff->value,
            'template' => null,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'customer_name' => 'Standalone Customer',
            'customer_representative' => null,
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => $this->oneItem(),
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // create — happy
    // -------------------------------------------------------------------------

    public function test_create_standalone_invoice_snapshots_customer_and_supplier(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $invoice = app(InvoiceService::class)->create($this->upsertData());

        $this->assertSame('Standalone Customer', $invoice->customer_name);
        $this->assertSame($tenant->name, $invoice->supplier_name);
        $this->assertSame($tenant->iban, $invoice->supplier_iban);
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->status);
        $this->assertSame($tenant->id, $invoice->tenant_id);
    }

    public function test_create_client_linked_invoice_snapshots_client_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme s.r.o.']);

        $invoice = app(InvoiceService::class)->create($this->upsertData(['client_id' => $client->id, 'customer_name' => null]));

        $this->assertSame('Acme s.r.o.', $invoice->customer_name);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame($client->ico, $invoice->customer_ico);
    }

    public function test_create_object_linked_invoice_snapshots_object_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Main Office']);

        $invoice = app(InvoiceService::class)->create($this->upsertData([
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'customer_name' => null,
        ]));

        $this->assertSame($object->id, $invoice->cleaning_object_id);
        $this->assertSame('Main Office', $invoice->object_name);
    }

    // -------------------------------------------------------------------------
    // create — failure
    // -------------------------------------------------------------------------

    public function test_store_standalone_without_customer_name_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storeHttpPayload(['customer_name' => null]);

        $this->post(route('invoices.store'), $payload)->assertSessionHasErrors('customer_name');
    }

    public function test_store_object_of_another_client_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

        $payload = $this->storeHttpPayload(['client_id' => $clientA->id, 'cleaning_object_id' => $object->id, 'customer_name' => null]);

        $this->post(route('invoices.store'), $payload)->assertSessionHasErrors('cleaning_object_id');
    }

    public function test_store_cross_tenant_client_id_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $other = Tenant::factory()->create();
        $foreignClient = Client::factory()->create(['tenant_id' => $other->id]);

        $payload = $this->storeHttpPayload(['client_id' => $foreignClient->id, 'customer_name' => null]);

        $this->post(route('invoices.store'), $payload)->assertSessionHasErrors('client_id');
    }

    public function test_store_monthly_without_period_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storeHttpPayload(['type' => InvoiceTypeEnum::Monthly->value]);

        $this->post(route('invoices.store'), $payload)->assertSessionHasErrors(['period_from', 'period_to']);
    }

    public function test_update_issued_invoice_throws_validation_exception(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->update($invoice, $this->upsertData());
    }

    // -------------------------------------------------------------------------
    // create — edge
    // -------------------------------------------------------------------------

    public function test_create_non_vat_payer_tenant_zeroes_item_vat_and_breakdown(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false, 'vat_rate' => 0]);
        $this->bindTenant($tenant);

        $invoice = app(InvoiceService::class)->create($this->upsertData());
        $invoice->loadMissing('items');

        $this->assertFalse($invoice->is_vat_payer);
        $this->assertNull($invoice->vat_breakdown);
        $this->assertSame('0.00', $invoice->items->sole()->line_vat);
    }

    public function test_create_full_discount_zeroes_line_base(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $items = [new InvoiceItemData(
            id: null,
            description: 'Fully discounted',
            quantity: 1,
            unit: null,
            unit_price: 100,
            discount_percent: 100,
            vat_rate: 23,
        )];

        $invoice = app(InvoiceService::class)->create($this->upsertData(['items' => $items]));
        $invoice->loadMissing('items');

        $this->assertSame('0.00', $invoice->items->sole()->line_base);
        $this->assertSame('0.00', $invoice->subtotal);
    }

    public function test_store_discount_over_100_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storeHttpPayload([
            'items' => [
                ['id' => null, 'description' => 'Item', 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 150, 'vat_rate' => 23],
            ],
        ]);

        $this->post(route('invoices.store'), $payload)->assertSessionHasErrors('items.0.discount_percent');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeHttpPayload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'type' => InvoiceTypeEnum::OneOff->value,
            'template' => null,
            'issue_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'period_from' => null,
            'period_to' => null,
            'customer_name' => 'HTTP Customer',
            'customer_representative' => null,
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => [
                ['id' => null, 'description' => 'Item', 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
            ],
            'constant_symbol' => null,
            'specific_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ], $overrides);
    }
}
