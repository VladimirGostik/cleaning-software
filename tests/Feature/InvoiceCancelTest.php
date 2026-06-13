<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceCancelTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_cancel_issued_invoice_creates_credit_note(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Test Client',
            'subtotal' => '200.00',
            'vat_amount' => '0.00',
            'total' => '200.00',
        ]);

        $response = $this->post(route('invoices.cancel', $invoice));

        $response->assertRedirect();
        $invoice->refresh();

        $this->assertEquals(InvoiceStatusEnum::Cancelled, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);

        // Credit note created
        $creditNote = Invoice::withoutGlobalScopes()
            ->where('credited_invoice_id', $invoice->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($creditNote);
        $this->assertEquals(InvoiceStatusEnum::Issued, $creditNote->status);
        $this->assertEquals(-200.0, (float) $creditNote->total);
        $this->assertEquals($invoice->customer_name, $creditNote->customer_name);
    }

    public function test_cancel_overdue_invoice_works(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->overdue()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->post(route('invoices.cancel', $invoice));

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(InvoiceStatusEnum::Cancelled, $invoice->status);
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_cancel_draft_invoice_returns_error(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('invoices.cancel', $invoice));

        // ValidationException from service → redirected back with session errors
        $response->assertRedirect();
        $response->assertSessionHasErrors('status');
    }

    public function test_user_without_cancel_permission_gets_403(): void
    {
        // Upratovačka role has no cancel invoices permission
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();

        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('invoices.cancel', $invoice));

        $response->assertForbidden();
    }
}
