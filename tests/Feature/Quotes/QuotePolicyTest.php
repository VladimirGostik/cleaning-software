<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Policies\QuotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotePolicyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // viewAny — ViewQuotes permission
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_view_any_quotes(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->assertTrue((new QuotePolicy)->viewAny($user));
    }

    public function test_upratovacka_cannot_view_any_quotes(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->assertFalse((new QuotePolicy)->viewAny($user));
    }

    // -------------------------------------------------------------------------
    // create — CreateQuotes permission
    // -------------------------------------------------------------------------

    public function test_sekretarka_can_create_quotes(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');

        $this->assertTrue((new QuotePolicy)->create($user));
    }

    // -------------------------------------------------------------------------
    // update — EditQuotes + isEditable
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_update_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $this->assertTrue((new QuotePolicy)->update($user, $quote));
    }

    public function test_vlastnik_cannot_update_sent_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertFalse((new QuotePolicy)->update($user, $quote));
    }

    // -------------------------------------------------------------------------
    // delete — DeleteQuotes + isEditable
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_delete_draft_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'status' => QuoteStatusEnum::Draft]);

        $this->assertTrue((new QuotePolicy)->delete($user, $quote));
    }

    public function test_upratovacka_cannot_delete_any_quote(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Use a different tenant's client so we don't need Pro for scope
        $otherTenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $quote = Quote::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $client->id]);

        $this->assertFalse((new QuotePolicy)->delete($user, $quote));
    }

    // -------------------------------------------------------------------------
    // send / accept / reject
    // -------------------------------------------------------------------------

    public function test_sekretarka_can_send_quote(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue((new QuotePolicy)->send($user, $quote));
    }

    public function test_sekretarka_can_accept_quote(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue((new QuotePolicy)->accept($user, $quote));
    }

    // -------------------------------------------------------------------------
    // downloadPdf — ViewQuotes
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_download_pdf(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue((new QuotePolicy)->downloadPdf($user, $quote));
    }

    // -------------------------------------------------------------------------
    // convertToInvoice / convertToContract — cross-module permissions
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_convert_to_invoice(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue((new QuotePolicy)->convertToInvoice($user, $quote));
    }

    public function test_sekretarka_without_create_invoices_cannot_convert_to_invoice(): void
    {
        // Sekretárka has Quotes permissions but NOT CreateInvoices in base template
        $user = $this->actingAsTenantUser('Sekretárka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertFalse((new QuotePolicy)->convertToInvoice($user, $quote));
    }

    // -------------------------------------------------------------------------
    // attachClient — EditQuotes permission (D5)
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_attach_client(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue((new QuotePolicy)->attachClient($user, $quote));
    }

    public function test_upratovacka_cannot_attach_client(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->withoutClient()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse((new QuotePolicy)->attachClient($user, $quote));
    }

    public function test_vlastnik_can_attach_client_on_sent_quote(): void
    {
        // D5 — attachClient is allowed in any status, only gated by client_id being null
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->withoutClient()->sent()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue((new QuotePolicy)->attachClient($user, $quote));
    }
}
