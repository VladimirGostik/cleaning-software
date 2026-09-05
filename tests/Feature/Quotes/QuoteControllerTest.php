<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
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
        $user = $this->actingAsTenantUser('Admin');
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
        $user = $this->actingAsTenantUser('Admin');
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
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $response = $this->get(route('quotes.index'));

        $response->assertForbidden();
    }

    public function test_index_filters_by_kind(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->makeQuotes($tenant, 2);
        Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index', ['filter' => ['kind' => 'document']]));

        $response->assertInertia(fn (Assert $page) => $page->has('quotes.data', 1));
    }

    // -------------------------------------------------------------------------
    // create page
    // -------------------------------------------------------------------------

    public function test_create_page_has_required_props(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('quotes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Create')
            ->has('clients')
            ->has('objects')
            ->has('currencyOptions')
            ->has('kindOptions')
            ->has('isVatPayer')
            ->has('vatRateOptions'),
        );
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_quote_and_redirects_to_show(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.store'), [
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'subject' => 'Test ponuka',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'kind' => 'itemized',
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
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.store'), [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'kind' => 'itemized',
            'currency' => 'EUR',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_store_creates_clientless_quote_with_customer_name(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('quotes.store'), [
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'kind' => 'itemized',
            'currency' => 'EUR',
            'customer_name' => 'Firma XYZ s.r.o.',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quotes', ['client_id' => null, 'customer_name' => 'Firma XYZ s.r.o.']);
    }

    public function test_store_rejects_client_and_customer_name_together(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.store'), [
            'client_id' => $client->id,
            'customer_name' => 'Firma XYZ s.r.o.',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'kind' => 'itemized',
            'currency' => 'EUR',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_store_rejects_when_neither_client_nor_customer_name(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('quotes.store'), [
            'client_id' => null,
            'customer_name' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'kind' => 'itemized',
            'currency' => 'EUR',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('customer_name');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_quote_detail(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Show')
            ->has('quote')
            ->where('clients', null)
            ->where('objects', null),
        );
    }

    public function test_show_returns_client_and_object_options_for_clientless_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Show')
            ->has('clients')
            ->has('objects'),
        );
    }

    public function test_show_returns_404_for_other_tenant_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $quote = Quote::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // update — kind immutability (D7)
    // -------------------------------------------------------------------------

    public function test_update_rejects_changing_kind(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $response = $this->put(route('quotes.update', $quote), [
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'subject' => null,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'kind' => 'document',
            'currency' => 'EUR',
            'items' => [],
        ]);

        $response->assertSessionHasErrors('kind');
    }

    public function test_update_accepts_same_kind(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $response = $this->put(route('quotes.update', $quote), [
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'subject' => 'Updated subject',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'kind' => 'itemized',
            'currency' => 'EUR',
            'items' => [
                ['name' => 'X', 'quantity' => 1, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'subject' => 'Updated subject']);
    }

    // -------------------------------------------------------------------------
    // send / accept / reject
    // -------------------------------------------------------------------------

    public function test_send_transitions_draft_to_sent(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $response = $this->post(route('quotes.send', $quote));

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => QuoteStatusEnum::Sent->value]);
    }

    public function test_accept_transitions_sent_to_accepted(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('quotes.accept', $quote));

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => QuoteStatusEnum::Accepted->value]);
    }

    // -------------------------------------------------------------------------
    // attach-client
    // -------------------------------------------------------------------------

    public function test_attach_client_sets_client_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->withoutClient()->sent()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.attach-client', $quote), [
            'client_id' => $client->id,
        ]);

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'client_id' => $client->id, 'customer_name' => null]);
    }

    public function test_attach_client_422_when_already_has_client(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('quotes.attach-client', $quote), [
            'client_id' => $otherClient->id,
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_attach_client_422_when_object_not_owned_by_client(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $otherClient->id]);

        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.attach-client', $quote), [
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
        ]);

        $response->assertSessionHasErrors('cleaning_object_id');
    }

    public function test_attach_client_403_without_edit_permission(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.attach-client', $quote), [
            'client_id' => $client->id,
        ]);

        $response->assertForbidden();
    }

    public function test_attach_client_404_for_other_tenant_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $otherTenant = Tenant::factory()->create();
        $otherQuote = Quote::factory()->withoutClient()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->post(route('quotes.attach-client', $otherQuote), [
            'client_id' => $client->id,
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
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
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'subject' => 'Original']);

        $response = $this->post(route('quotes.duplicate', $quote));

        $response->assertRedirect();
        $this->assertDatabaseCount('quotes', 2);
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
