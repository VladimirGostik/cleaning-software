<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuotePeriodTotalsData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Events\QuoteSent;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Services\QuoteService;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AssertsFullRows;
use Tests\TestCase;

final class QuoteServiceTest extends TestCase
{
    use AssertsFullRows;
    use RefreshDatabase;

    /** @return array<int, QuoteItemData> */
    private function oneItem(): array
    {
        return [new QuoteItemData(
            id: null,
            description: 'Cleaning service',
            frequency: null,
            quantity: 2,
            unit: 'hod',
            unit_price: 30,
            discount_percent: 0,
            vat_rate: 23,
        )];
    }

    /** @param  array<string, mixed>  $overrides */
    private function upsertData(array $overrides = []): QuoteUpsertData
    {
        return QuoteUpsertData::from(array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Office cleaning',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => $this->oneItem(),
            'customer_name' => 'Standalone Customer',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ], $overrides));
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
            'subject' => 'HTTP quote',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [
                ['id' => null, 'description' => 'Item', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
            ],
            'customer_name' => 'HTTP Customer',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // create — happy
    // -------------------------------------------------------------------------

    public function test_create_with_items_computes_totals_and_no_number(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');

        $this->assertNull($quote->number);
        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
        $this->assertSame('60.00', $quote->subtotal);
        $this->assertSame($tenant->id, $quote->tenant_id);
    }

    public function test_create_with_manual_number(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(['number' => 'CP-2026-0001']), null, 'sess-1');

        $this->assertSame('CP-2026-0001', $quote->number);
    }

    public function test_create_computes_vat_for_vat_payer_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true, 'vat_rate' => 23]);
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');
        $quote->loadMissing('items');

        $this->assertTrue($quote->is_vat_payer);
        $this->assertSame('13.80', $quote->vat_amount);
        $this->assertNotNull($quote->vat_breakdown);
    }

    public function test_create_clientless_stores_customer_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');

        $this->assertSame('Standalone Customer', $quote->customer_name);
        $this->assertNull($quote->client_id);
    }

    public function test_create_with_client_ignores_customer_snapshot_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme s.r.o.']);

        $quote = app(QuoteService::class)->create(
            $this->upsertData(['client_id' => $client->id, 'customer_name' => null]),
            null,
            'sess-1',
        );

        $this->assertSame($client->id, $quote->client_id);
        $this->assertNull($quote->customer_name);
    }

    public function test_create_and_update_persist_item_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $itemWithNote = [new QuoteItemData(
            id: null,
            description: 'Cleaning service',
            frequency: null,
            quantity: 2,
            unit: 'hod',
            unit_price: 30,
            discount_percent: 0,
            vat_rate: 23,
            note: 'Bring extra supplies',
        )];

        $created = app(QuoteService::class)->create($this->upsertData(['items' => $itemWithNote]), null, 'sess-1');
        $created->loadMissing('items');

        $this->assertSame('Bring extra supplies', $created->items->sole()->note);

        $quote = Quote::findOrFail($created->id);
        $updatedItem = [new QuoteItemData(
            id: null,
            description: 'Cleaning service',
            frequency: null,
            quantity: 2,
            unit: 'hod',
            unit_price: 30,
            discount_percent: 0,
            vat_rate: 23,
            note: 'Updated note',
        )];

        $updated = app(QuoteService::class)->update($quote, $this->upsertData(['items' => $updatedItem]), null, 'sess-1');
        $updated->loadMissing('items');

        $this->assertSame('Updated note', $updated->items->sole()->note);
    }

    // -------------------------------------------------------------------------
    // create — validation (number uniqueness) — HTTP level
    // -------------------------------------------------------------------------

    public function test_store_duplicate_number_in_same_tenant_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        Quote::factory()->numbered()->create(['tenant_id' => $tenant->id, 'number' => 'CP-2026-0100']);

        $payload = $this->storeHttpPayload(['number' => 'CP-2026-0100']);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('number');
    }

    public function test_store_same_number_different_tenant_succeeds(): void
    {
        $other = Tenant::factory()->create();
        Quote::factory()->numbered()->create(['tenant_id' => $other->id, 'number' => 'CP-2026-0200']);

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storeHttpPayload(['number' => 'CP-2026-0200']);

        $this->post(route('quotes.store'), $payload)->assertSessionDoesntHaveErrors('number');
    }

    public function test_store_reuses_number_of_soft_deleted_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $old = Quote::factory()->numbered()->create(['tenant_id' => $tenant->id, 'number' => 'CP-2026-0300']);
        $old->delete();

        $payload = $this->storeHttpPayload(['number' => 'CP-2026-0300']);

        $this->post(route('quotes.store'), $payload)->assertSessionDoesntHaveErrors('number');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_upsert_data_rejects_three_decimal_places_but_accepts_two(): void
    {
        app()->setLocale('sk');

        $payload = fn (array $itemOverrides): array => [
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Decimal precision quote',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [
                array_merge(
                    ['id' => null, 'description' => 'Item', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
                    $itemOverrides,
                ),
            ],
            'customer_name' => 'Decimal Precision Customer',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ];

        foreach (['unit_price', 'quantity', 'discount_percent'] as $field) {
            $errorKey = "items.0.{$field}";

            foreach (['18.125', 18.125] as $rejectedValue) {
                try {
                    QuoteUpsertData::validateAndCreate($payload([$field => $rejectedValue]));
                    $this->fail("Expected a ValidationException for {$field}=".var_export($rejectedValue, true));
                } catch (ValidationException $e) {
                    $this->assertDecimalPrecisionMessage($e, $errorKey);
                }
            }

            $acceptedTwoDecimals = QuoteUpsertData::validateAndCreate($payload([$field => 18.13]));
            $this->assertSame(18.13, $acceptedTwoDecimals->items[0]->{$field});

            $acceptedWhole = QuoteUpsertData::validateAndCreate($payload([$field => 18]));
            $this->assertSame(18.0, $acceptedWhole->items[0]->{$field});
        }
    }

    private function assertDecimalPrecisionMessage(ValidationException $e, string $key): void
    {
        $errors = $e->errors();
        $this->assertArrayHasKey($key, $errors);

        $messages = $errors[$key];
        $this->assertIsArray($messages);
        $this->assertArrayHasKey(0, $messages);

        $message = $messages[0];
        $this->assertIsString($message);
        $this->assertStringContainsString('desatinných', $message);
        $this->assertStringNotContainsString('decimal:0,2', $message);
    }

    public function test_update_recomputes_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $created = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');
        $quote = Quote::findOrFail($created->id);

        $newItems = [new QuoteItemData(
            id: null,
            description: 'Bigger job',
            frequency: null,
            quantity: 5,
            unit: 'hod',
            unit_price: 20,
            discount_percent: 0,
            vat_rate: 0,
        )];

        $updated = app(QuoteService::class)->update($quote, $this->upsertData(['items' => $newItems]), null, 'sess-1');
        $updated->loadMissing('items');

        $this->assertSame('100.00', $updated->subtotal);
    }

    public function test_update_fails_for_accepted_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'status' => QuoteStatusEnum::Accepted]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->update($quote, $this->upsertData(), null, 'sess-1');
    }

    public function test_update_of_sent_quote_returns_it_to_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $updated = app(QuoteService::class)->update($quote, $this->upsertData(['subject' => 'Opravená ponuka']), null, 'sess-1');

        $this->assertSame(QuoteStatusEnum::Draft, $updated->status);
        $this->assertNull($updated->sent_at);
        $this->assertSame('Opravená ponuka', $updated->subject);
    }

    public function test_delete_fails_for_sent_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->delete($quote);
    }

    // -------------------------------------------------------------------------
    // lifecycle transitions
    // -------------------------------------------------------------------------

    public function test_send_transitions_draft_to_sent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        RoleTemplatesSeeder::seedForTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $sent = app(QuoteService::class)->send($quote);

        $this->assertSame(QuoteStatusEnum::Sent, $sent->status);
        $this->assertNotNull($sent->sent_at);
    }

    public function test_send_fails_for_invalid_transition(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->send($quote);
    }

    public function test_send_dispatches_quote_sent_event(): void
    {
        Event::fake([QuoteSent::class]);
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        app(QuoteService::class)->send($quote);

        Event::assertDispatched(QuoteSent::class, fn (QuoteSent $e) => $e->quoteId === $quote->id && $e->tenantId === $tenant->id);
    }

    public function test_accept_transitions_sent_to_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $accepted = app(QuoteService::class)->accept($quote);

        $this->assertSame(QuoteStatusEnum::Accepted, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
    }

    public function test_reject_transitions_sent_to_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $rejected = app(QuoteService::class)->reject($quote);

        $this->assertSame(QuoteStatusEnum::Rejected, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
    }

    // -------------------------------------------------------------------------
    // attach client
    // -------------------------------------------------------------------------

    public function test_attach_client_sets_client_and_clears_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id, 'customer_name' => 'Rough lead']);

        $attached = app(QuoteService::class)->attachClient($quote, $client->id, null);

        $this->assertSame($client->id, $attached->client_id);
        $this->assertNull($attached->customer_name);
    }

    public function test_attach_client_works_when_quote_is_sent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->withoutClient()->sent()->create(['tenant_id' => $tenant->id]);

        $attached = app(QuoteService::class)->attachClient($quote, $client->id, null);

        $this->assertSame($client->id, $attached->client_id);
    }

    public function test_attach_client_fails_when_client_already_set(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->attachClient($quote, $otherClient->id, null);
    }

    // -------------------------------------------------------------------------
    // duplicate
    // -------------------------------------------------------------------------

    public function test_duplicate_leaves_number_null_and_resets_dates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->numbered()->create(['tenant_id' => $tenant->id]);

        $duplicate = app(QuoteService::class)->duplicate($quote);

        $this->assertNull($duplicate->number);
        $this->assertSame(QuoteStatusEnum::Draft, $duplicate->status);
        $this->assertSame(now()->toDateString(), $duplicate->issue_date->toDateString());
        $this->assertSame(now()->addDays(30)->toDateString(), $duplicate->valid_until->toDateString());
    }

    public function test_duplicate_keeps_snapshot_and_copies_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');

        $duplicate = app(QuoteService::class)->duplicate($quote);
        $duplicate->loadMissing('items');

        $this->assertSame('Standalone Customer', $duplicate->customer_name);
        $this->assertCount(1, $duplicate->items);
    }

    public function test_duplicate_copies_item_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $itemWithNote = [new QuoteItemData(
            id: null,
            description: 'Cleaning service',
            frequency: null,
            quantity: 2,
            unit: 'hod',
            unit_price: 30,
            discount_percent: 0,
            vat_rate: 23,
            note: 'Fragile items in kitchen',
        )];
        $quote = app(QuoteService::class)->create($this->upsertData(['items' => $itemWithNote]), null, 'sess-1');

        $duplicate = app(QuoteService::class)->duplicate($quote);
        $duplicate->loadMissing('items');

        $this->assertSame('Fragile items in kitchen', $duplicate->items->sole()->note);
    }

    // -------------------------------------------------------------------------
    // delete
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_draft_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        app(QuoteService::class)->delete($quote);

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_delete_fails_for_accepted_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->delete($quote);
    }

    // -------------------------------------------------------------------------
    // paginate
    // -------------------------------------------------------------------------

    public function test_paginate_scopes_by_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        Quote::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Quote::factory()->count(3)->create(['tenant_id' => $other->id]);
        $this->bindTenant($tenant);

        $paginator = app(QuoteService::class)->paginate(request());

        $this->assertSame(2, $paginator->total());
    }

    public function test_paginate_eager_loads_object_and_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        Quote::factory()->forClient($client)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $paginator = app(QuoteService::class)->paginate(request());

        $this->assertSame(1, $paginator->total());
    }

    /** @return array<int, QuoteItemData> */
    private function periodItems(): array
    {
        $make = fn (string $description, float $qty, float $price, QuoteBillingPeriodEnum $period): QuoteItemData => new QuoteItemData(
            id: null,
            description: $description,
            frequency: null,
            quantity: $qty,
            unit: null,
            unit_price: $price,
            discount_percent: 0,
            vat_rate: 23,
            billing_period: $period,
        );

        return [
            $make('Yearly windows', 1, 200, QuoteBillingPeriodEnum::Yearly),
            $make('Monthly a', 1, 70, QuoteBillingPeriodEnum::Monthly),
            $make('Setup', 1, 300, QuoteBillingPeriodEnum::OneOff),
            $make('Monthly b', 2, 25, QuoteBillingPeriodEnum::Monthly),
            $make('Quarterly', 1, 80, QuoteBillingPeriodEnum::Quarterly),
        ];
    }

    public function test_period_totals_group_items_in_enum_order_and_keep_overall_total(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(['items' => $this->periodItems()]), null, 'sess-1');

        $this->assertSame('700.00', $quote->total);
        $this->assertEquals(
            [
                ['billing_period' => 'one_off', 'subtotal' => '300.00', 'vat_amount' => '0.00', 'total' => '300.00'],
                ['billing_period' => 'monthly', 'subtotal' => '120.00', 'vat_amount' => '0.00', 'total' => '120.00'],
                ['billing_period' => 'quarterly', 'subtotal' => '80.00', 'vat_amount' => '0.00', 'total' => '80.00'],
                ['billing_period' => 'yearly', 'subtotal' => '200.00', 'vat_amount' => '0.00', 'total' => '200.00'],
            ],
            $quote->fresh()?->period_totals,
        );
    }

    public function test_period_totals_hold_vat_per_group_for_vat_payer(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true, 'vat_rate' => 23]);
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(['items' => $this->periodItems()]), null, 'sess-1');

        $totals = QuotePeriodTotalsData::collectFrom($quote->fresh()?->period_totals);
        $byPeriod = [];
        foreach ($totals as $row) {
            $byPeriod[$row->billing_period->value] = [$row->subtotal, $row->vat_amount, $row->total];
        }

        $this->assertSame([
            'one_off' => ['300.00', '69.00', '369.00'],
            'monthly' => ['120.00', '27.60', '147.60'],
            'quarterly' => ['80.00', '18.40', '98.40'],
            'yearly' => ['200.00', '46.00', '246.00'],
        ], $byPeriod);
    }

    public function test_group_without_items_is_absent_from_period_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $items = array_values(array_filter($this->periodItems(), fn (QuoteItemData $i): bool => $i->billing_period === QuoteBillingPeriodEnum::Monthly));
        $quote = app(QuoteService::class)->create($this->upsertData(['items' => $items]), null, 'sess-1');

        $this->assertSame(['monthly'], array_column((array) $quote->fresh()?->period_totals, 'billing_period'));
    }

    public function test_update_recomputes_period_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $service = app(QuoteService::class);
        $quote = $service->create($this->upsertData(['items' => $this->periodItems()]), null, 'sess-1');

        $service->update($quote, $this->upsertData(), null, 'sess-1');

        $this->assertSame(['one_off'], array_column((array) $quote->fresh()?->period_totals, 'billing_period'));
    }

    public function test_document_kind_quote_has_no_period_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $this->assertNull($quote->fresh()?->period_totals);
        $this->assertSame([], QuotePeriodTotalsData::collectFrom($quote->period_totals));
    }

    public function test_included_text_is_stored_and_split_into_trimmed_lines(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create(
            $this->upsertData(['included_text' => "  Materials \n\n\r\nTravel  \n   "]),
            null,
            'sess-1',
        );

        $this->assertSame(['Materials', 'Travel'], $quote->includedLines());
    }

    public function test_cleared_included_text_yields_no_lines(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(['included_text' => '']), null, 'sess-1');

        $this->assertSame([], $quote->includedLines());
    }

    public function test_duplicate_copies_billing_period_period_totals_and_included_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $service = app(QuoteService::class);
        $quote = $service->create($this->upsertData(['items' => $this->periodItems(), 'included_text' => 'Materials']), null, 'sess-1');

        $copy = $service->duplicate($quote->fresh() ?? $quote);

        $this->assertSame('Materials', $copy->included_text);
        $this->assertEquals($quote->fresh()?->period_totals, $copy->fresh()?->period_totals);
        $this->assertEqualsCanonicalizing(
            ['monthly', 'monthly', 'one_off', 'quarterly', 'yearly'],
            $copy->items->map(fn (QuoteItem $i): string => $i->billing_period->value)->all(),
        );
    }

    public function test_item_billing_period_defaults_to_one_off(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create($this->upsertData(), null, 'sess-1');

        $this->assertSame(QuoteBillingPeriodEnum::OneOff, $quote->items->first()?->billing_period);
    }

    public function test_billing_period_enum_display_helpers(): void
    {
        app()->setLocale('sk');

        $this->assertNull(QuoteBillingPeriodEnum::OneOff->adverb());
        $this->assertFalse(QuoteBillingPeriodEnum::OneOff->isRecurring());
        $this->assertSame('mesačne', QuoteBillingPeriodEnum::Monthly->adverb());
        $this->assertSame('mesiac', QuoteBillingPeriodEnum::Monthly->unitNoun());
        $this->assertSame('mes.', QuoteBillingPeriodEnum::Monthly->shortSuffix());
        $this->assertSame('štvrťrok', QuoteBillingPeriodEnum::Quarterly->unitNoun());
        $this->assertSame('ročne', QuoteBillingPeriodEnum::Yearly->adverb());
        $this->assertSame('rok', QuoteBillingPeriodEnum::Yearly->shortSuffix());
        $this->assertSame(RecurringFrequencyEnum::Annually, QuoteBillingPeriodEnum::Yearly->recurringFrequency());
        $this->assertNull(QuoteBillingPeriodEnum::OneOff->recurringFrequency());
    }

    // -------------------------------------------------------------------------
    // duplicate — full column characterization
    // -------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function fullQuoteItemColumns(): array
    {
        return [
            [
                'description' => 'One-off deep clean', 'frequency' => 'one_time', 'billing_period' => 'one_off', 'note' => 'Bring ladder',
                'quantity' => '1.00', 'unit' => 'ks', 'unit_price' => '200.00', 'discount_percent' => '0.00', 'vat_rate' => '20.00',
                'line_base' => '200.00', 'line_vat' => '40.00', 'line_total' => '240.00', 'position' => 0,
            ],
            [
                'description' => 'Monthly office', 'frequency' => 'weekly_2x', 'billing_period' => 'monthly', 'note' => null,
                'quantity' => '8.50', 'unit' => 'hod', 'unit_price' => '12.00', 'discount_percent' => '10.00', 'vat_rate' => '20.00',
                'line_base' => '91.80', 'line_vat' => '18.36', 'line_total' => '110.16', 'position' => 1,
            ],
            [
                'description' => 'Quarterly windows', 'frequency' => null, 'billing_period' => 'quarterly', 'note' => 'All floors',
                'quantity' => '4.00', 'unit' => null, 'unit_price' => '30.00', 'discount_percent' => '0.00', 'vat_rate' => '0.00',
                'line_base' => '120.00', 'line_vat' => '0.00', 'line_total' => '120.00', 'position' => 2,
            ],
            [
                'description' => 'Yearly carpets', 'frequency' => 'seasonal', 'billing_period' => 'yearly', 'note' => null,
                'quantity' => '1.00', 'unit' => 'ks', 'unit_price' => '500.00', 'discount_percent' => '5.00', 'vat_rate' => '20.00',
                'line_base' => '475.00', 'line_vat' => '95.00', 'line_total' => '570.00', 'position' => 3,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function carriedQuoteColumns(): array
    {
        return [
            'kind' => 'itemized',
            'subject' => 'Annual cleaning offer',
            'customer_name' => 'Lead s.r.o.',
            'customer_email' => 'lead@example.test',
            'customer_street' => 'Leadova 5',
            'customer_city' => 'Zvolen',
            'customer_postal_code' => '960 01',
            'customer_represented_by' => 'Správca s.r.o.',
            'customer_ico' => '35683073',
            'customer_dic' => '2020905865',
            'show_totals' => false,
            'show_vat_note' => false,
            'is_vat_payer' => true,
            'vat_rate' => '20.00',
            'currency' => 'CZK',
            'subtotal' => '886.80',
            'vat_amount' => '153.36',
            'total' => '1040.16',
            'vat_breakdown' => [['rate' => 20.0, 'base' => 766.8, 'vat' => 153.36, 'total' => 920.16]],
            'period_totals' => [
                ['billing_period' => 'one_off', 'subtotal' => '200.00', 'vat_amount' => '40.00', 'total' => '240.00'],
                ['billing_period' => 'monthly', 'subtotal' => '91.80', 'vat_amount' => '18.36', 'total' => '110.16'],
            ],
            'note' => 'Internal quote note',
            'included_text' => "Materials\nTravel",
        ];
    }

    private function fullQuoteSource(Tenant $tenant, string $status): Quote
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->create([
            ...$this->carriedQuoteColumns(),
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'status' => $status,
            'number' => 'CP-2026-0007',
            'issue_date' => '2026-10-01',
            'valid_until' => '2026-10-31',
            'sent_at' => '2026-10-02 09:00:00',
            'accepted_at' => '2026-10-05 11:00:00',
            'rejected_at' => '2026-10-06 12:00:00',
        ]);

        foreach ($this->fullQuoteItemColumns() as $columns) {
            QuoteItem::factory()->create([...$columns, 'tenant_id' => $tenant->id, 'quote_id' => $quote->id]);
        }

        return $quote->load('items');
    }

    /** @return array<string, array{0: string}> */
    public static function duplicableStatuses(): array
    {
        return ['sent' => ['sent'], 'accepted' => ['accepted']];
    }

    #[DataProvider('duplicableStatuses')]
    public function test_duplicate_copies_every_carried_column_and_resets_lifecycle_columns(string $status): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0, 0));
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = $this->fullQuoteSource($tenant, $status);

        $copy = app(QuoteService::class)->duplicate($source);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertFullRow($copy, [
            ...$this->carriedQuoteColumns(),
            'tenant_id' => $source->tenant_id,
            'client_id' => $source->client_id,
            'cleaning_object_id' => $source->cleaning_object_id,
            'status' => 'draft',
            'number' => null,
            'issue_date' => '2026-11-04',
            'valid_until' => '2026-12-04',
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_duplicate_copies_items_of_every_billing_period_column_by_column(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = $this->fullQuoteSource($tenant, 'accepted');

        $copy = app(QuoteService::class)->duplicate($source);

        $this->assertTrue($copy->relationLoaded('items'));
        $this->assertCount(4, $copy->items);

        $copyItems = $copy->items->all();
        $sourceItems = $source->items->all();

        foreach ($this->fullQuoteItemColumns() as $index => $columns) {
            $this->assertNotSame($sourceItems[$index]->id, $copyItems[$index]->id);
            $this->assertFullRow($copyItems[$index], [
                ...$columns,
                'tenant_id' => $source->tenant_id,
                'quote_id' => $copy->id,
            ]);
        }

        $this->assertSame(4, QuoteItem::query()->where('quote_id', $source->id)->count());
    }

    public function test_duplicate_leaves_the_source_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = $this->fullQuoteSource($tenant, 'sent');
        $before = (array) DB::table('quotes')->where('id', $source->id)->first();

        app(QuoteService::class)->duplicate($source);

        $this->assertEquals($before, (array) DB::table('quotes')->where('id', $source->id)->first());
    }

    public function test_duplicate_writes_a_created_activity_row_with_the_logged_attributes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = $this->fullQuoteSource($tenant, 'accepted');

        $copy = app(QuoteService::class)->duplicate($source);

        $this->assertSame([
            'status' => 'draft',
            'kind' => 'itemized',
            'number' => null,
            'total' => '1040.16',
            'client_id' => $source->client_id,
            'cleaning_object_id' => $source->cleaning_object_id,
        ], $this->createdActivityAttributes($copy));
    }

    public function test_duplicate_of_document_quote_copies_row_but_no_items(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = Quote::factory()->document()->accepted()->numbered()->create(['tenant_id' => $tenant->id, 'included_text' => null]);

        $copy = app(QuoteService::class)->duplicate($source);

        $this->assertSame(QuoteKindEnum::Document, $copy->kind);
        $this->assertSame(QuoteStatusEnum::Draft, $copy->status);
        $this->assertNull($copy->number);
        $this->assertNull($copy->accepted_at);
        $this->assertCount(0, $copy->items);
    }

    public function test_duplicate_valid_until_follows_configured_validity_days(): void
    {
        config(['quotes.default_validity_days' => 10]);
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0, 0));
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $source = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $copy = app(QuoteService::class)->duplicate($source);

        $this->assertSame('2026-11-14', $copy->valid_until->toDateString());
    }

    public function test_preview_model_is_unsaved_with_priced_items_and_totals(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $this->bindTenant($tenant);
        $data = QuoteUpsertData::from([
            'client_id' => null, 'cleaning_object_id' => null, 'subject' => 'S', 'issue_date' => '2026-11-11', 'valid_until' => '2026-12-11', 'note' => null,
            'customer_name' => 'Acme', 'customer_ico' => '1', 'show_totals' => false, 'kind' => 'itemized', 'currency' => 'EUR', 'number' => null,
            'items' => [
                ['id' => null, 'description' => 'A', 'frequency' => null, 'quantity' => 2, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0, 'billing_period' => 'one_off'],
                ['id' => null, 'description' => 'B', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 5, 'discount_percent' => 0, 'vat_rate' => 0, 'billing_period' => 'monthly'],
            ],
        ]);

        $quote = app(QuoteService::class)->previewModel($data);

        $this->assertFalse($quote->exists);
        $this->assertSame(0, Quote::query()->count());
        $this->assertSame(0, QuoteItem::query()->count());
        $this->assertCount(2, $quote->items);
        $this->assertSame('25.00', (string) $quote->total);
        $this->assertSame(['one_off', 'monthly'], array_column((array) $quote->period_totals, 'billing_period'));
        $this->assertFalse($quote->show_totals);
        $this->assertSame('1', $quote->customer_ico);
    }
}
