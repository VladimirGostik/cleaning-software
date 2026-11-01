<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\DocumentTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class QuoteShowContractTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_itemized_quote_exposes_active_service_agreement_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        DocumentTemplate::factory()->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::ServiceAgreement]);
        DocumentTemplate::factory()->inactive()->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::ServiceAgreement]);
        DocumentTemplate::factory()->employment()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->has('contractTemplates', 1));
    }

    public function test_draft_quote_exposes_no_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);
        DocumentTemplate::factory()->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::ServiceAgreement]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->has('contractTemplates', 0));
    }

    public function test_document_kind_quote_exposes_no_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->document()->accepted()->create(['tenant_id' => $tenant->id]);
        DocumentTemplate::factory()->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::ServiceAgreement]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->has('contractTemplates', 0));
    }

    public function test_actor_without_create_contracts_permission_sees_no_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Účtovníčka', $tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        DocumentTemplate::factory()->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::ServiceAgreement]);

        $response = $this->get(route('quotes.show', $quote));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Quotes/Show')->has('contractTemplates', 0));
    }
}
