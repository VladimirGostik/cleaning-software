<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteKindEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class QuoteControllerTest extends TestCase
{
    use RefreshDatabase;

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
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_tenant_quotes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        Quote::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 3),
        );
    }

    public function test_index_excludes_other_tenant_quotes(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        Quote::factory()->count(2)->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('quotes.index'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 0),
        );
    }

    public function test_index_forbidden_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('quotes.index'))->assertForbidden();
    }

    public function test_index_filters_by_kind(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        Quote::factory()->create(['tenant_id' => $tenant->id, 'kind' => QuoteKindEnum::Itemized]);
        Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index', ['filter' => ['kind' => 'document']]));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 1),
        );
    }

    public function test_index_filters_by_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        Quote::factory()->create(['tenant_id' => $tenant->id]);
        Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index', ['filter' => ['status' => 'sent']]));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 1),
        );
    }

    public function test_index_filters_by_client_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Quote::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);
        Quote::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index', ['filter' => ['client_id' => $client->id]]));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 1),
        );
    }

    public function test_index_search_matches_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Findable Client']);
        Quote::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);
        Quote::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.index', ['filter' => ['search' => 'Findable']]));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Quotes/Index')->has('quotes.data', 1),
        );
    }

    // -------------------------------------------------------------------------
    // create / store
    // -------------------------------------------------------------------------

    public function test_create_returns_form_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('quotes.create'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Create')->has('context'));
    }

    public function test_store_redirects_to_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->post(route('quotes.store'), $this->storeHttpPayload());

        $response->assertRedirect();
        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_store_and_update_persist_item_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $payload = $this->storeHttpPayload([
            'items' => [
                ['id' => null, 'description' => 'Item', 'frequency' => null, 'note' => 'Handle with care', 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
            ],
        ]);

        $this->post(route('quotes.store'), $payload)->assertSessionDoesntHaveErrors();

        $quote = Quote::query()->sole();
        $this->assertSame('Handle with care', $quote->items()->sole()->note);

        $updatePayload = $this->storeHttpPayload([
            'items' => [
                ['id' => null, 'description' => 'Item', 'frequency' => null, 'note' => 'Updated note', 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
            ],
        ]);

        $this->put(route('quotes.update', $quote), $updatePayload)->assertSessionDoesntHaveErrors();

        $this->assertSame('Updated note', $quote->items()->sole()->note);
    }

    public function test_store_forbidden_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->post(route('quotes.store'), $this->storeHttpPayload())->assertForbidden();
    }

    public function test_store_clientless_ok(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('quotes.store'), $this->storeHttpPayload())->assertSessionDoesntHaveErrors();
    }

    public function test_store_client_and_customer_name_fails_prohibits(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $payload = $this->storeHttpPayload(['client_id' => $client->id, 'customer_name' => 'Should not be set']);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('client_id');
    }

    public function test_store_neither_client_nor_customer_name_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->storeHttpPayload(['customer_name' => null]);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('customer_name');
    }

    public function test_store_object_of_another_client_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

        $payload = $this->storeHttpPayload(['client_id' => $clientA->id, 'cleaning_object_id' => $object->id, 'customer_name' => null]);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('cleaning_object_id');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_returns_detail(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->where('quote.id', $quote->id));
    }

    public function test_show_offers_attach_options_only_when_clientless(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->where('clients', null)->where('objects', null));
    }

    public function test_show_404_for_other_tenant_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $quote = Quote::factory()->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('quotes.show', $quote))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_kind_change_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'kind' => QuoteKindEnum::Itemized]);

        $payload = $this->storeHttpPayload(['kind' => QuoteKindEnum::Document->value]);

        $this->put(route('quotes.update', $quote), $payload)->assertSessionHasErrors('kind');
    }

    public function test_update_same_kind_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'kind' => QuoteKindEnum::Itemized]);

        $payload = $this->storeHttpPayload(['subject' => 'Updated subject']);

        $this->put(route('quotes.update', $quote), $payload)->assertSessionDoesntHaveErrors();
        $quote->refresh();
        $this->assertSame('Updated subject', $quote->subject);
    }

    // -------------------------------------------------------------------------
    // lifecycle actions
    // -------------------------------------------------------------------------

    public function test_send_redirects_with_success(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.send', $quote))->assertRedirect(route('quotes.show', $quote));
    }

    public function test_accept_redirects_with_success(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.accept', $quote))->assertRedirect(route('quotes.show', $quote));
    }

    // -------------------------------------------------------------------------
    // attach-client
    // -------------------------------------------------------------------------

    public function test_attach_client_succeeds(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.attach-client', $quote), ['client_id' => $client->id])
            ->assertRedirect(route('quotes.show', $quote));
        $quote->refresh();
        $this->assertSame($client->id, $quote->client_id);
    }

    public function test_attach_client_fails_when_already_has_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $other = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->forClient($client)->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.attach-client', $quote), ['client_id' => $other->id])
            ->assertSessionHasErrors('client_id');
    }

    public function test_attach_client_fails_when_object_not_of_client(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $otherClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $otherClient->id]);
        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.attach-client', $quote), ['client_id' => $client->id, 'cleaning_object_id' => $object->id])
            ->assertSessionHasErrors('cleaning_object_id');
    }

    public function test_attach_client_forbidden_without_edit_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.attach-client', $quote), ['client_id' => $client->id])->assertForbidden();
    }

    public function test_attach_client_404_for_other_tenant_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('quotes.attach-client', $quote), ['client_id' => $client->id])->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // destroy / duplicate
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('quotes.destroy', $quote))->assertRedirect(route('quotes.index'));
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_duplicate_redirects_to_edit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->numbered()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.duplicate', $quote));

        $response->assertRedirectContains('/quotes/');
        $this->assertDatabaseCount('quotes', 2);
    }

    // -------------------------------------------------------------------------
    // pdf
    // -------------------------------------------------------------------------

    public function test_pdf_404_for_other_tenant_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $quote = Quote::factory()->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('quotes.pdf', $quote))->assertNotFound();
    }
}
