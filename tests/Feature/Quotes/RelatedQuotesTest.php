<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\PermissionEnum;
use App\Enums\QuoteKindEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RelatedQuotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_and_object_pages_list_their_quotes(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();
        $object = CleaningObject::factory()->create(['client_id' => $client->id]);
        $onObject = Quote::factory()->create(['client_id' => $client->id, 'cleaning_object_id' => $object->id]);
        $onClient = Quote::factory()->create(['client_id' => $client->id, 'cleaning_object_id' => null]);
        Quote::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->get(route('clients.show', $client))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('quotes', 2)
            ->where('quotes', fn (Collection $quotes): bool => $quotes->pluck('id')->sort()->values()->all() === collect([$onObject->id, $onClient->id])->sort()->values()->all())
            ->where('canCreateQuote', true));

        $this->get(route('objects.show', $object))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('quotes', 1)
            ->where('quotes.0.id', $onObject->id));
    }

    public function test_quotes_are_null_without_view_quotes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        Role::inTenant($tenant->id)->where('name', 'Sekretárka')->firstOrFail()
            ->revokePermissionTo([PermissionEnum::ViewQuotes->value, PermissionEnum::CreateQuotes->value]);
        $client = Client::factory()->create();

        $this->get(route('clients.show', $client))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quotes', null)
            ->where('canCreateQuote', false));
    }

    public function test_create_prefills_the_object_with_its_client_and_the_kind(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();
        $object = CleaningObject::factory()->create(['client_id' => $client->id]);

        $this->get(route('quotes.create', ['kind' => 'document', 'cleaning_object_id' => $object->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.kind', QuoteKindEnum::Document->value)
                ->where('prefill.client_id', $client->id)
                ->where('prefill.cleaning_object_id', $object->id));
    }

    public function test_create_drops_a_client_of_another_company_and_an_unknown_kind(): void
    {
        $this->actingAsTenantUser();
        $foreign = Client::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->get(route('quotes.create', ['kind' => 'nonsense', 'client_id' => $foreign->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.kind', null)
                ->where('prefill.client_id', null)
                ->where('prefill.cleaning_object_id', null));
    }
}
