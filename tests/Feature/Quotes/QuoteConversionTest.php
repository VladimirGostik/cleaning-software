<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Invoices\InvoiceDetailData;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\TaskFrequencyEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'frequency' => TaskFrequencyEnum::Weekly1x,
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame('draft', $invoice->status->value);
        $this->assertSame('Weekly cleaning (1x weekly)', $invoice->items->sole()->description);
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
            'frequency' => TaskFrequencyEnum::Monthly,
            'note' => 'Use eco-friendly products',
        ]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);
        $invoice->loadMissing('items');

        $this->assertSame('Deep cleaning (Monthly) — Use eco-friendly products', $invoice->items->sole()->description);
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

    public function test_converted_invoice_inherits_tenant_default_header_and_footer_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        TenantInterface::factory()->create([
            'tenant_id' => $tenant->id,
            'default_header_text' => 'Default header',
            'default_footer_text' => 'Default footer',
        ]);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertSame('Default header', $invoice->header_text);
        $this->assertSame('Default footer', $invoice->footer_text);
    }

    public function test_converted_invoice_header_and_footer_text_null_without_tenant_interface_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertNull($invoice->header_text);
        $this->assertNull($invoice->footer_text);
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

    /**
     * @param  array<string, mixed>  $quoteState
     * @param  list<QuoteBillingPeriodEnum>  $periods
     */
    private function acceptedQuoteWith(Tenant $tenant, array $periods, array $quoteState = []): Quote
    {
        $quote = Quote::factory()->accepted()->create(array_merge(['tenant_id' => $tenant->id], $quoteState));

        foreach ($periods as $position => $period) {
            QuoteItem::factory()->create([
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'description' => $period->value.' item',
                'billing_period' => $period,
                'position' => $position,
            ]);
        }

        return $quote;
    }

    private function statusError(callable $callback): string
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            return $e->validator->errors()->first('status');
        }

        $this->fail('Expected a ValidationException.');
    }

    public function test_convert_to_invoice_copies_only_one_off_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = $this->acceptedQuoteWith($tenant, [QuoteBillingPeriodEnum::OneOff, QuoteBillingPeriodEnum::Monthly, QuoteBillingPeriodEnum::Yearly]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertSame(['one_off item'], $invoice->items()->pluck('description')->all());
    }

    public function test_convert_to_invoice_fails_when_quote_has_only_recurring_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = $this->acceptedQuoteWith($tenant, [QuoteBillingPeriodEnum::Monthly]);

        $message = $this->statusError(fn () => app(QuoteService::class)->convertToInvoice($quote));

        $this->assertSame(__('app.quote_has_no_one_off_items'), $message);
        $this->assertDatabaseCount('invoices', 0);
    }

    /** @return array<string, array{QuoteBillingPeriodEnum, RecurringFrequencyEnum}> */
    public static function recurringPeriods(): array
    {
        return [
            'monthly' => [QuoteBillingPeriodEnum::Monthly, RecurringFrequencyEnum::Monthly],
            'quarterly' => [QuoteBillingPeriodEnum::Quarterly, RecurringFrequencyEnum::Quarterly],
            'yearly' => [QuoteBillingPeriodEnum::Yearly, RecurringFrequencyEnum::Annually],
        ];
    }

    #[DataProvider('recurringPeriods')]
    public function test_recurring_prefill_maps_frequency_and_holds_only_that_group(QuoteBillingPeriodEnum $period, RecurringFrequencyEnum $frequency): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = $this->acceptedQuoteWith(
            $tenant,
            [QuoteBillingPeriodEnum::OneOff, QuoteBillingPeriodEnum::Monthly, QuoteBillingPeriodEnum::Quarterly, QuoteBillingPeriodEnum::Yearly],
            ['client_id' => $client->id, 'subject' => 'Office'],
        );

        $prefill = app(QuoteService::class)->recurringInvoicePrefill($quote, $period);

        $this->assertSame($frequency, $prefill->frequency);
        $this->assertSame([$period->value.' item'], array_map(fn ($i) => $i->description, $prefill->items));
        $this->assertSame($client->id, $prefill->client_id);
        $this->assertNull($prefill->customer_name);
        $this->assertSame('Office – '.$period->label(), $prefill->name);
    }

    public function test_recurring_prefill_for_clientless_quote_fills_customer_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = $this->acceptedQuoteWith($tenant, [QuoteBillingPeriodEnum::Monthly], [
            'client_id' => null,
            'customer_name' => 'Walk-in s.r.o.',
            'customer_email' => 'walk@in.sk',
            'customer_street' => 'Hlavná 1',
            'customer_city' => 'Zvolen',
            'customer_postal_code' => '960 01',
        ]);

        $prefill = app(QuoteService::class)->recurringInvoicePrefill($quote, QuoteBillingPeriodEnum::Monthly);

        $this->assertNull($prefill->client_id);
        $this->assertSame(['Walk-in s.r.o.', 'walk@in.sk', 'Hlavná 1', 'Zvolen', '960 01'], [
            $prefill->customer_name, $prefill->customer_email, $prefill->customer_street, $prefill->customer_city, $prefill->customer_postal_code,
        ]);
    }

    public function test_recurring_prefill_rejects_one_off_and_empty_groups(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = $this->acceptedQuoteWith($tenant, [QuoteBillingPeriodEnum::OneOff, QuoteBillingPeriodEnum::Monthly]);
        $service = app(QuoteService::class);

        $this->assertSame(__('app.quote_has_no_items_for_period'), $this->statusError(fn () => $service->recurringInvoicePrefill($quote, QuoteBillingPeriodEnum::OneOff)));
        $this->assertSame(__('app.quote_has_no_items_for_period'), $this->statusError(fn () => $service->recurringInvoicePrefill($quote, QuoteBillingPeriodEnum::Yearly)));
    }

    public function test_recurring_prefill_guards_status_and_kind(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $service = app(QuoteService::class);

        foreach (['draft' => Quote::factory(), 'sent' => Quote::factory()->sent(), 'rejected' => Quote::factory()->rejected()] as $factory) {
            $quote = $factory->create(['tenant_id' => $tenant->id]);
            QuoteItem::factory()->monthly()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

            $this->assertSame(__('app.quote_not_acceptable_for_conversion'), $this->statusError(fn () => $service->recurringInvoicePrefill($quote, QuoteBillingPeriodEnum::Monthly)));
        }

        $document = Quote::factory()->document()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->assertSame(__('app.quote_document_not_convertible'), $this->statusError(fn () => $service->recurringInvoicePrefill($document, QuoteBillingPeriodEnum::Monthly)));
    }

    public function test_long_item_note_is_capped_to_255_chars_on_invoice_and_recurring_prefill(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        foreach ([QuoteBillingPeriodEnum::OneOff, QuoteBillingPeriodEnum::Monthly] as $position => $period) {
            QuoteItem::factory()->create([
                'tenant_id' => $tenant->id,
                'quote_id' => $quote->id,
                'description' => str_repeat('d', 255),
                'note' => str_repeat('n', 500),
                'billing_period' => $period,
                'position' => $position,
            ]);
        }
        $service = app(QuoteService::class);

        $invoice = $service->convertToInvoice($quote);
        $prefill = $service->recurringInvoicePrefill($quote, QuoteBillingPeriodEnum::Monthly);

        $this->assertSame(255, mb_strlen($invoice->items()->sole()->description));
        $this->assertSame(255, mb_strlen($prefill->items[0]->description));
        $this->assertStringEndsWith('…', $prefill->items[0]->description);
    }

    public function test_clientless_conversion_maps_all_five_recipient_fields_and_nulls_the_rest(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->withoutClient()->accepted()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Rough Lead s.r.o.',
            'customer_email' => 'lead@example.test',
            'customer_street' => 'Main 1',
            'customer_city' => 'Zvolen',
            'customer_postal_code' => '960 01',
        ]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = Invoice::query()->findOrFail(app(QuoteService::class)->convertToInvoice($quote)->id);

        $this->assertSame([
            'client_id' => null,
            'cleaning_object_id' => null,
            'customer_name' => 'Rough Lead s.r.o.',
            'customer_representative' => null,
            'customer_represented_by' => null,
            'customer_ico' => null,
            'customer_peppol_participant_id' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => 'Main 1',
            'customer_city' => 'Zvolen',
            'customer_postal_code' => '960 01',
            'customer_country' => 'SK',
            'customer_email' => 'lead@example.test',
            'quote_id' => $quote->id,
        ], $invoice->only([
            'client_id', 'cleaning_object_id', 'customer_name', 'customer_representative', 'customer_represented_by', 'customer_ico',
            'customer_peppol_participant_id', 'customer_dic', 'customer_vat_number', 'customer_street', 'customer_city',
            'customer_postal_code', 'customer_country', 'customer_email', 'quote_id',
        ]));
    }

    public function test_clientless_conversion_with_empty_recipient_falls_back_to_empty_name_and_sk_country(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->withoutClient()->accepted()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => null,
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
        ]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = Invoice::query()->findOrFail(app(QuoteService::class)->convertToInvoice($quote)->id);

        $this->assertSame('', $invoice->customer_name);
        $this->assertSame('SK', $invoice->customer_country);
        $this->assertNull($invoice->customer_email);
        $this->assertNull($invoice->customer_street);
        $this->assertNull($invoice->customer_city);
        $this->assertNull($invoice->customer_postal_code);
    }

    public function test_client_linked_conversion_takes_the_client_snapshot_and_ignores_stale_quote_recipient_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Client Corp',
            'ico' => '11112222',
            'dic' => '2011112222',
            'vat_number' => 'SK2011112222',
            'street' => 'Klientska 4',
            'city' => 'Nitra',
            'postal_code' => '949 01',
            'country' => 'CZ',
        ]);
        $quote = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'customer_name' => 'Stale name',
            'customer_email' => 'stale@example.test',
            'customer_street' => 'Stale 1',
            'customer_city' => 'Stale city',
            'customer_postal_code' => '000 00',
        ]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = Invoice::query()->findOrFail(app(QuoteService::class)->convertToInvoice($quote)->id);

        $this->assertSame([
            'client_id' => $client->id,
            'customer_name' => 'Client Corp',
            'customer_representative' => null,
            'customer_represented_by' => null,
            'customer_ico' => '11112222',
            'customer_peppol_participant_id' => $client->peppol_participant_id,
            'customer_dic' => '2011112222',
            'customer_vat_number' => 'SK2011112222',
            'customer_street' => 'Klientska 4',
            'customer_city' => 'Nitra',
            'customer_postal_code' => '949 01',
            'customer_country' => 'CZ',
            'customer_email' => null,
        ], $invoice->only([
            'client_id', 'customer_name', 'customer_representative', 'customer_represented_by', 'customer_ico',
            'customer_peppol_participant_id', 'customer_dic', 'customer_vat_number', 'customer_street', 'customer_city',
            'customer_postal_code', 'customer_country', 'customer_email',
        ]));
    }
}
