<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_quotes_for_tenant(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->makeQuotes($tenant, 3);

        $response = $this->get(route('quotes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Index')
            ->has('quotes.data', 3)
            ->has('filters')
            ->has('statusOptions')
            ->has('clients'),
        );
    }

    public function test_index_excludes_other_tenant_quotes(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->makeQuotes($tenant, 2);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        Quote::factory()->count(5)->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $response = $this->get(route('quotes.index'));

        $response->assertInertia(fn (Assert $page) => $page->has('quotes.data', 2));
    }

    public function test_index_403_without_view_quotes_permission(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('quotes.index'));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create page
    // -------------------------------------------------------------------------

    public function test_create_page_has_required_props(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('quotes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Create')
            ->has('clients')
            ->has('objects')
            ->has('currencyOptions')
            ->has('isVatPayer')
            ->has('vatRateOptions'),
        );
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_quote_and_redirects_to_show(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.store'), [
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'subject' => 'Test ponuka',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'currency' => 'EUR',
            'items' => [
                [
                    'name' => 'Upratovanie',
                    'description' => null,
                    'frequency' => null,
                    'quantity' => 2,
                    'unit' => 'hod',
                    'unit_price' => 20,
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quotes', ['client_id' => $client->id, 'subject' => 'Test ponuka']);
    }

    public function test_store_403_without_create_permission(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.store'), [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'EUR',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_quote_detail(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Show')
            ->has('quote'),
        );
    }

    // -------------------------------------------------------------------------
    // send / accept / reject
    // -------------------------------------------------------------------------

    public function test_send_transitions_draft_to_sent(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $response = $this->post(route('quotes.send', $quote));

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => QuoteStatusEnum::Sent->value]);
    }

    public function test_accept_transitions_sent_to_accepted(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('quotes.accept', $quote));

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => QuoteStatusEnum::Accepted->value]);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $response = $this->delete(route('quotes.destroy', $quote));

        $response->assertRedirect(route('quotes.index'));
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    // -------------------------------------------------------------------------
    // duplicate
    // -------------------------------------------------------------------------

    public function test_duplicate_redirects_to_edit_of_new_quote(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'subject' => 'Original']);

        $response = $this->post(route('quotes.duplicate', $quote));

        $response->assertRedirect();
        $this->assertDatabaseCount('quotes', 2);
    }

    // -------------------------------------------------------------------------
    // Feature-gating: 403 when quotes feature disabled (Starter plan)
    // -------------------------------------------------------------------------

    public function test_index_redirected_when_quotes_feature_not_available(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        // Starter plan does not have quotes feature
        $this->setUserPlan($user, SubscriptionPlanEnum::Starter);

        $response = $this->get(route('quotes.index'));

        // Feature middleware returns 403
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeQuotes(Tenant $tenant, int $count): void
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Quote::factory()->count($count)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
    }
}
