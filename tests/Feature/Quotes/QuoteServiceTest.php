<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteIndexFilterData;
use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create — happy path (no auto-numbering)
    // -------------------------------------------------------------------------

    public function test_create_stores_quote_with_items_and_no_number(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: 'Test ponuka',
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(
                    id: null,
                    name: 'Upratovanie',
                    description: null,
                    frequency: null,
                    quantity: 2.0,
                    unit: 'hod',
                    unit_price: 20.0,
                    discount_percent: 0.0,
                    vat_rate: 0.0,
                ),
            ],
            currency: CurrencyEnum::EUR,
        );

        $quote = app(QuoteService::class)->create($data);

        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
        $this->assertNull($quote->number);
        $this->assertSame('40.00', $quote->total);
        $this->assertCount(1, $quote->items);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'client_id' => $client->id, 'number' => null]);
    }

    public function test_create_stores_manually_entered_number(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: null,
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(id: null, name: 'A', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 10.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
            number: 'CP2026-0099',
            currency: CurrencyEnum::EUR,
        );

        $quote = app(QuoteService::class)->create($data);

        $this->assertSame('CP2026-0099', $quote->number);
    }

    // -------------------------------------------------------------------------
    // create — number uniqueness (DTO validation)
    // -------------------------------------------------------------------------

    public function test_create_rejects_duplicate_number_within_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'number' => 'CP2026-DUP',
        ]);

        $payload = $this->validQuotePayload($client->id, ['number' => 'CP2026-DUP']);
        $this->bindFakeRequest($payload);

        $this->expectException(ValidationException::class);

        QuoteUpsertData::validate($payload);
    }

    public function test_create_allows_same_number_in_different_tenants(): void
    {
        $ownerA = $this->actingAsTenantUser('Admin');
        $tenantA = Tenant::where('owner_id', $ownerA->id)->first();
        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);

        Quote::factory()->create([
            'tenant_id' => $tenantA->id,
            'client_id' => $clientA->id,
            'number' => 'CP2026-SHARED',
        ]);

        $ownerB = $this->actingAsTenantUser('Admin');
        $tenantB = Tenant::where('owner_id', $ownerB->id)->first();
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

        $payload = $this->validQuotePayload($clientB->id, ['number' => 'CP2026-SHARED']);
        $this->bindFakeRequest($payload);

        $this->assertIsArray(QuoteUpsertData::validate($payload));
    }

    public function test_create_allows_reusing_number_from_soft_deleted_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $deleted = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'number' => 'CP2026-REUSE',
        ]);
        $deleted->delete();

        $payload = $this->validQuotePayload($client->id, ['number' => 'CP2026-REUSE']);
        $this->bindFakeRequest($payload);

        $this->assertIsArray(QuoteUpsertData::validate($payload));
    }

    // -------------------------------------------------------------------------
    // create — clientless (customer snapshot)
    // -------------------------------------------------------------------------

    public function test_create_stores_clientless_quote_with_customer_snapshot(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $data = new QuoteUpsertData(
            client_id: null,
            cleaning_object_id: null,
            subject: 'Rýchla ponuka',
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(id: null, name: 'A', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 10.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
            customer_name: 'Firma XYZ s.r.o.',
            customer_email: 'info@xyz.sk',
            customer_street: 'Hlavná 1',
            customer_city: 'Bratislava',
            customer_postal_code: '81101',
        );

        $quote = app(QuoteService::class)->create($data);

        $this->assertNull($quote->client_id);
        $this->assertSame('Firma XYZ s.r.o.', $quote->customer_name);
        $this->assertSame('info@xyz.sk', $quote->customer_email);
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'client_id' => null,
            'customer_name' => 'Firma XYZ s.r.o.',
        ]);
    }

    public function test_create_ignores_customer_snapshot_when_client_present(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: null,
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(id: null, name: 'A', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 10.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
        );

        $quote = app(QuoteService::class)->create($data);

        $this->assertNull($quote->customer_name);
    }

    // -------------------------------------------------------------------------
    // attachToClient
    // -------------------------------------------------------------------------

    public function test_attach_to_client_sets_client_and_clears_snapshot(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote = Quote::factory()->sent()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $result = app(QuoteService::class)->attachToClient($quote, $client->id, $object->id);

        $this->assertSame($client->id, $result->client_id);
        $this->assertSame($object->id, $result->cleaning_object_id);
        $this->assertNull($result->customer_name);
        $this->assertNull($result->customer_email);
        $this->assertNull($result->customer_street);
        $this->assertNull($result->customer_city);
        $this->assertNull($result->customer_postal_code);
        $this->assertSame(QuoteStatusEnum::Sent, $result->status);
    }

    public function test_attach_to_client_works_in_every_status(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        foreach (['sent', 'accepted', 'rejected', 'expired'] as $state) {
            $quote = Quote::factory()->{$state}()->withoutClient()->create(['tenant_id' => $tenant->id]);

            $result = app(QuoteService::class)->attachToClient($quote, $client->id);

            $this->assertSame($client->id, $result->client_id);
            $this->assertNull($result->cleaning_object_id);
        }
    }

    public function test_attach_to_client_throws_when_client_already_set(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->attachToClient($quote, $otherClient->id);
    }

    // -------------------------------------------------------------------------
    // update — happy path
    // -------------------------------------------------------------------------

    public function test_update_changes_items_and_recomputes_totals(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => QuoteStatusEnum::Draft,
        ]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: 'Updated',
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(14)->toDateString(),
            note: 'New note',
            items: [
                new QuoteItemData(id: null, name: 'Nová položka', description: null, frequency: null, quantity: 5.0, unit: 'ks', unit_price: 10.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
            currency: CurrencyEnum::EUR,
        );

        $updated = app(QuoteService::class)->update($quote, $data);

        $this->assertSame('Updated', $updated->subject);
        $this->assertSame('50.00', $updated->total);
        $this->assertCount(1, $updated->items);
        $this->assertSame('Nová položka', $updated->items->first()->name);
    }

    // -------------------------------------------------------------------------
    // update — fail: not editable
    // -------------------------------------------------------------------------

    public function test_update_throws_when_quote_is_sent(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: null,
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(id: null, name: 'X', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 1.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
            currency: CurrencyEnum::EUR,
        );

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->update($quote, $data);
    }

    // -------------------------------------------------------------------------
    // lifecycle: send / accept / reject
    // -------------------------------------------------------------------------

    public function test_send_transitions_draft_to_sent(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $result = app(QuoteService::class)->send($quote);

        $this->assertSame(QuoteStatusEnum::Sent, $result->status);
        $this->assertNotNull($result->sent_at);
    }

    public function test_accept_transitions_sent_to_accepted(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $result = app(QuoteService::class)->accept($quote);

        $this->assertSame(QuoteStatusEnum::Accepted, $result->status);
        $this->assertNotNull($result->accepted_at);
    }

    public function test_reject_transitions_sent_to_rejected(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $result = app(QuoteService::class)->reject($quote);

        $this->assertSame(QuoteStatusEnum::Rejected, $result->status);
        $this->assertNotNull($result->rejected_at);
    }

    public function test_send_throws_when_already_sent(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->send($quote);
    }

    // -------------------------------------------------------------------------
    // duplicate
    // -------------------------------------------------------------------------

    public function test_duplicate_leaves_number_null(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $original = Quote::factory()->accepted()->numbered()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'subject' => 'Original',
        ]);

        $dupe = app(QuoteService::class)->duplicate($original);

        $this->assertSame(QuoteStatusEnum::Draft, $dupe->status);
        $this->assertNotSame($original->id, $dupe->id);
        $this->assertNull($dupe->number);
        $this->assertSame('Original', $dupe->subject);
    }

    public function test_duplicate_keeps_customer_snapshot_of_clientless_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $original = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $dupe = app(QuoteService::class)->duplicate($original);

        $this->assertNull($dupe->client_id);
        $this->assertSame($original->customer_name, $dupe->customer_name);
    }

    // -------------------------------------------------------------------------
    // delete — happy + fail
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(QuoteService::class)->delete($quote);

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_delete_throws_when_quote_is_accepted(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->delete($quote);
    }

    // -------------------------------------------------------------------------
    // paginate — tenant isolation
    // -------------------------------------------------------------------------

    public function test_paginate_returns_only_current_tenant_quotes(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        Quote::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        Quote::factory()->count(5)->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $paginator = app(QuoteService::class)->paginate(new QuoteIndexFilterData);

        $this->assertSame(3, $paginator->total());
    }

    // -------------------------------------------------------------------------
    // VAT calculation
    // -------------------------------------------------------------------------

    public function test_create_computes_vat_correctly_for_vat_payer_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['is_vat_payer' => true, 'vat_rate' => '23.00']);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $data = new QuoteUpsertData(
            client_id: $client->id,
            cleaning_object_id: null,
            subject: null,
            issue_date: now()->toDateString(),
            valid_until: now()->addDays(30)->toDateString(),
            note: null,
            items: [
                new QuoteItemData(id: null, name: 'Upratovanie', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 100.0, discount_percent: 0.0, vat_rate: 23.0),
            ],
            currency: CurrencyEnum::EUR,
        );

        $quote = app(QuoteService::class)->create($data);

        $this->assertTrue($quote->is_vat_payer);
        $this->assertSame('100.00', $quote->subtotal);
        $this->assertSame('23.00', $quote->vat_amount);
        $this->assertSame('123.00', $quote->total);
        $this->assertNotNull($quote->vat_breakdown);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validQuotePayload(string $clientId, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $clientId,
            'cleaning_object_id' => null,
            'subject' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [
                ['name' => 'A', 'quantity' => 1, 'unit_price' => 10],
            ],
            'kind' => 'itemized',
            'currency' => 'EUR',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindFakeRequest(array $payload): void
    {
        app()->instance('request', Request::create('/quotes', 'POST', $payload));
    }
}
