<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteIndexFilterData;
use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create — happy path
    // -------------------------------------------------------------------------

    public function test_create_stores_quote_with_number_and_items(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        $this->assertNotNull($quote->number);
        $this->assertStringStartsWith('CP', $quote->number);
        $this->assertSame('40.00', $quote->total);
        $this->assertCount(1, $quote->items);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'client_id' => $client->id]);
    }

    // -------------------------------------------------------------------------
    // create — number uniqueness (dedupe loop)
    // -------------------------------------------------------------------------

    public function test_create_assigns_unique_number_per_tenant(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $baseData = [
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'subject' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'currency' => CurrencyEnum::EUR,
            'items' => [
                new QuoteItemData(id: null, name: 'A', description: null, frequency: null, quantity: 1.0, unit: null, unit_price: 10.0, discount_percent: 0.0, vat_rate: 0.0),
            ],
        ];

        $q1 = app(QuoteService::class)->create(new QuoteUpsertData(...$baseData));
        $q2 = app(QuoteService::class)->create(new QuoteUpsertData(...$baseData));

        $this->assertNotSame($q1->number, $q2->number);
    }

    // -------------------------------------------------------------------------
    // update — happy path
    // -------------------------------------------------------------------------

    public function test_update_changes_items_and_recomputes_totals(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $result = app(QuoteService::class)->send($quote);

        $this->assertSame(QuoteStatusEnum::Sent, $result->status);
        $this->assertNotNull($result->sent_at);
    }

    public function test_accept_transitions_sent_to_accepted(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $result = app(QuoteService::class)->accept($quote);

        $this->assertSame(QuoteStatusEnum::Accepted, $result->status);
        $this->assertNotNull($result->accepted_at);
    }

    public function test_reject_transitions_sent_to_rejected(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $result = app(QuoteService::class)->reject($quote);

        $this->assertSame(QuoteStatusEnum::Rejected, $result->status);
        $this->assertNotNull($result->rejected_at);
    }

    public function test_send_throws_when_already_sent(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->send($quote);
    }

    // -------------------------------------------------------------------------
    // duplicate
    // -------------------------------------------------------------------------

    public function test_duplicate_creates_new_draft_with_fresh_number(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $original = Quote::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'subject' => 'Original',
        ]);

        $dupe = app(QuoteService::class)->duplicate($original);

        $this->assertSame(QuoteStatusEnum::Draft, $dupe->status);
        $this->assertNotSame($original->id, $dupe->id);
        $this->assertNotSame($original->number, $dupe->number);
        $this->assertSame('Original', $dupe->subject);
    }

    // -------------------------------------------------------------------------
    // delete — happy + fail
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(QuoteService::class)->delete($quote);

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_delete_throws_when_quote_is_accepted(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
}
