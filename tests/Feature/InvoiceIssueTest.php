<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceIssueTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path — auto-sequence
    // -------------------------------------------------------------------------

    public function test_issue_draft_invoice_assigns_number_and_status(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'issue_date' => now()->toDateString(),
        ]);

        $response = $this->post(route('invoices.issue', $invoice), []);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals(InvoiceStatusEnum::Issued, $invoice->status);
        $this->assertStringStartsWith('FA-' . date('Y') . '-', $invoice->number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->variable_symbol);
        $this->assertMatchesRegularExpression('/^\d+$/', $invoice->variable_symbol);
    }

    public function test_two_sequential_issues_get_consecutive_numbers(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);

        $inv1 = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);
        $inv2 = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $this->post(route('invoices.issue', $inv1), []);
        $this->post(route('invoices.issue', $inv2), []);

        $inv1->refresh();
        $inv2->refresh();

        $num1 = (int) substr($inv1->number, strrpos($inv1->number, '-') + 1);
        $num2 = (int) substr($inv2->number, strrpos($inv2->number, '-') + 1);

        $this->assertEquals(1, $num1);
        $this->assertEquals(2, $num2);
    }

    public function test_second_tenant_has_independent_sequence(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['invoice_number_format' => '{YYYY}-{XXXX}']);

        $inv1 = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);
        $this->post(route('invoices.issue', $inv1), []);

        // Switch to second tenant (its own user as owner)
        $user2 = $this->actingAsTenantUser('Admin');
        $tenant2 = Tenant::where('owner_id', $user2->id)->first();
        $tenant2->update(['invoice_number_format' => '{YYYY}-{XXXX}']);

        $inv2 = Invoice::factory()->create(['tenant_id' => $tenant2->id, 'issue_date' => now()->toDateString()]);
        $this->post(route('invoices.issue', $inv2), []);

        $inv2->refresh();
        $this->assertStringEndsWith('-0001', $inv2->number);
    }

    // -------------------------------------------------------------------------
    // Happy path — manual number
    // -------------------------------------------------------------------------

    public function test_manual_number_is_accepted_and_sequence_not_incremented(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $response = $this->post(route('invoices.issue', $invoice), ['number' => 'CUSTOM-2026-99']);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals('CUSTOM-2026-99', $invoice->number);
        // Sequence table should NOT have a row (no auto-assign happened)
        $seqCount = InvoiceNumberSequence::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();
        $this->assertEquals(0, $seqCount);
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_issue_already_issued_invoice_is_rejected(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('invoices.issue', $invoice), []);

        // ValidationException → redirect back with session errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
    }

    public function test_duplicate_manual_number_within_tenant_returns_error(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'number' => 'FA-2026-DUPE',
        ]);

        $newInvoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $response = $this->post(route('invoices.issue', $newInvoice), ['number' => 'FA-2026-DUPE']);

        $response->assertRedirect();
        $response->assertSessionHasErrors('number');
    }

    public function test_same_manual_number_in_different_tenant_is_allowed(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $otherTenant = Tenant::factory()->create();
        Invoice::factory()->issued()->create([
            'tenant_id' => $otherTenant->id,
            'number' => 'FA-2026-SHARED',
        ]);

        $newInvoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $response = $this->post(route('invoices.issue', $newInvoice), ['number' => 'FA-2026-SHARED']);

        $response->assertRedirect();
        $newInvoice->refresh();
        $this->assertEquals('FA-2026-SHARED', $newInvoice->number);
    }

    // -------------------------------------------------------------------------
    // Auto-number skips pre-existing manual number (BUG 5)
    // -------------------------------------------------------------------------

    public function test_auto_number_skips_manually_assigned_number(): void
    {
        // Reproduce BUG 5: a manually-issued invoice occupies the number the
        // sequence would produce next. The service must skip that slot and
        // produce the next free number instead of colliding.
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['invoice_number_format' => 'FA-{YYYY}-{XXXX}']);

        $year = now()->year;

        // Manually-issued invoice already occupies FA-YYYY-0001
        Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'number' => "FA-{$year}-0001",
        ]);

        // Auto-issue should skip 0001 and assign 0002
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'issue_date' => now()->toDateString(),
        ]);

        $response = $this->post(route('invoices.issue', $invoice), []);

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals("FA-{$year}-0002", $invoice->number);
    }

    // -------------------------------------------------------------------------
    // Numbering format presets
    // -------------------------------------------------------------------------

    public function test_format_yymm_xxx_renders_correctly(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $tenant->update(['invoice_number_format' => '{YY}{MM}{XXX}']);

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => now()->toDateString()]);

        $this->post(route('invoices.issue', $invoice), []);

        $invoice->refresh();
        $expected = now()->format('ym') . '001';
        $this->assertEquals($expected, $invoice->number);
    }
}
