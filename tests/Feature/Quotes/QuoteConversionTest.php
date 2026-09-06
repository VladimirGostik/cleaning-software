<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Invoices\InvoiceDetailData;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_converts_accepted_quote_to_draft_invoice_with_frequency_suffix(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Weekly cleaning',
            'frequency' => 'weekly',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame('draft', $invoice->status->value);
        $this->assertSame('Weekly cleaning (weekly)', $invoice->items->sole()->description);
    }

    public function test_converts_quote_item_note_into_invoice_item_description_suffix(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Deep cleaning',
            'frequency' => null,
            'note' => 'Use eco-friendly products',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame('Deep cleaning — Use eco-friendly products', $invoice->items->sole()->description);
    }

    public function test_converts_quote_item_with_both_frequency_and_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Deep cleaning',
            'frequency' => 'monthly',
            'note' => 'Use eco-friendly products',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame('Deep cleaning (monthly) — Use eco-friendly products', $invoice->items->sole()->description);
    }

    public function test_empty_note_does_not_add_suffix(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Deep cleaning',
            'frequency' => null,
            'note' => null,
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame('Deep cleaning', $invoice->items->sole()->description);
    }

    public function test_fails_when_quote_not_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToInvoice($quote);
    }

    public function test_fails_for_document_kind_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->document()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToInvoice($quote);
    }

    public function test_clientless_quote_carries_customer_snapshot_into_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->withoutClient()->accepted()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Rough Lead s.r.o.',
            'customer_street' => 'Main 1',
        ]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertNull($invoice->client_id);
        $this->assertSame('Rough Lead s.r.o.', $invoice->customer_name);
        $this->assertSame('Main 1', $invoice->customer_street);
    }

    public function test_reconversion_is_allowed_and_creates_second_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->forClient($client)->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        app(QuoteService::class)->convertToInvoice($quote);
        app(QuoteService::class)->convertToInvoice($quote);

        $this->assertSame(2, $quote->invoices()->count());
    }

    public function test_invoice_detail_data_exposes_source_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->numbered()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('quote');

        $detail = InvoiceDetailData::fromModel($invoice, null);

        $this->assertSame($quote->id, $detail->quote_id);
        $this->assertSame($quote->number, $detail->quote_number);
    }
}
