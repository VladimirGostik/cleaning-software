<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Events\QuoteSent;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteServiceTest extends TestCase
{
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

    public function test_update_fails_when_not_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->update($quote, $this->upsertData(), null, 'sess-1');
    }

    // -------------------------------------------------------------------------
    // lifecycle transitions
    // -------------------------------------------------------------------------

    public function test_send_transitions_draft_to_sent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
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
}
