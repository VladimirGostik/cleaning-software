<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoiceIssueData;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceIssuedByTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_issue_stamps_actors_membership_display_name(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['first_name' => 'Jana', 'last_name' => 'Nováková']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), $user);

        $this->assertSame('Jana Nováková', $issued->issued_by_name);
        $this->assertNotSame($tenant->name, $issued->issued_by_name);
    }

    public function test_issue_falls_back_to_user_name_when_membership_has_no_first_or_last_name(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant, User::factory()->create(['name' => 'Fallback User']));
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), $user);

        $this->assertSame('Fallback User', $issued->issued_by_name);
    }

    // -------------------------------------------------------------------------
    // failure — request input can never forge the issuer
    // -------------------------------------------------------------------------

    public function test_forged_issued_by_name_in_request_body_is_ignored(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['first_name' => 'Real', 'last_name' => 'Actor']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('invoices.issue', $invoice), [
            'number' => null,
            'issued_by_name' => 'Forged Impersonator',
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('Real Actor', $invoice->issued_by_name);
        $this->assertNotSame('Forged Impersonator', $invoice->issued_by_name);
    }

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_deactivated_and_removed_actor_still_keeps_original_issued_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['first_name' => 'Leaving', 'last_name' => 'Employee']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), $user);
        $this->assertSame('Leaving Employee', $issued->issued_by_name);

        TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)
            ->update(['is_active' => false]);
        TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->delete();

        $reread = Invoice::withoutGlobalScopes()->findOrFail($issued->id);
        $this->assertSame('Leaving Employee', $reread->issued_by_name);
    }

    public function test_draft_has_null_issued_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertNull($invoice->issued_by_name);
    }

    public function test_duplicate_of_issued_invoice_has_null_issued_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = app(InvoiceService::class)->issue($invoice, new InvoiceIssueData(number: null), $user);

        $duplicate = app(InvoiceService::class)->duplicate($issued);

        $this->assertNull($duplicate->issued_by_name);
    }

    public function test_quote_conversion_produces_draft_with_null_issued_by_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);
        QuoteItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

        $invoice = app(QuoteService::class)->convertToInvoice($quote);

        $this->assertNull($invoice->issued_by_name);
    }
}
