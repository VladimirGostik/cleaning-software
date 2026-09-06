<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceIssueData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSkFieldsTest extends TestCase
{
    use RefreshDatabase;

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
            'customer_name' => 'SK Fields Customer',
            'customer_representative' => 'Ján Novák',
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => [new InvoiceItemData(null, 'Item', 1, null, 100, 0, 23)],
            'constant_symbol' => '0308',
            'specific_symbol' => '1234567890',
            'header_text' => 'Header note',
            'footer_text' => 'Footer note',
            'deposit' => 25,
            'payment_type' => 'cash',
            'currency' => 'EUR',
            'rounding_mode' => 'cash_005',
        ], $overrides));
    }

    public function test_create_persists_sk_standard_fields(): void
    {
        $tenant = Tenant::factory()->create(['swift_bic' => 'TATRSKBX']);
        $this->bindTenant($tenant);

        $invoice = app(InvoiceService::class)->create($this->upsertData());

        $this->assertSame('0308', $invoice->constant_symbol);
        $this->assertSame('1234567890', $invoice->specific_symbol);
        $this->assertSame('cash', $invoice->payment_type->value);
        $this->assertSame('EUR', $invoice->currency->value);
        $this->assertSame('cash_005', $invoice->rounding_mode->value);
        $this->assertSame('Header note', $invoice->header_text);
        $this->assertSame('Footer note', $invoice->footer_text);
        $this->assertSame('25.00', $invoice->deposit);
        $this->assertSame('TATRSKBX', $invoice->supplier_swift);
        $this->assertSame('Ján Novák', $invoice->customer_representative);
    }

    public function test_issue_generates_variable_symbol_from_number(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null));

        $this->assertNotNull($issued->variable_symbol);
        $this->assertMatchesRegularExpression('/^\d+$/', $issued->variable_symbol);
    }

    public function test_balance_due_accessor_subtracts_deposit_from_total(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'total' => '123.00',
            'deposit' => '20.00',
        ]);

        $this->assertSame(103.0, $invoice->balance_due);
    }
}
