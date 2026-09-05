<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Jobs\GenerateRecurringInvoiceJob;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\TenantInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class GenerateRecurringInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    private function createDueTemplate(array $overrides = []): RecurringInvoice
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->toDateString(),
            'occurrences_generated' => 0,
            'customer_name' => 'Acme s.r.o.',
            'due_days' => 14,
        ], $overrides));

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantId,
            'recurring_invoice_id' => $ri->id,
            'description' => 'Monthly cleaning',
            'quantity' => 1.0,
            'unit_price' => 100.0,
            'position' => 0,
        ]);

        return $ri;
    }

    // -------------------------------------------------------------------------
    // Uniqueness contract
    // -------------------------------------------------------------------------

    public function test_job_implements_should_be_unique(): void
    {
        $this->assertInstanceOf(ShouldBeUnique::class, new GenerateRecurringInvoiceJob('some-uuid'));
    }

    public function test_unique_id_returns_recurring_invoice_id(): void
    {
        $id = 'recurring-invoice-uuid-123';
        $job = new GenerateRecurringInvoiceJob($id);

        $this->assertSame($id, $job->uniqueId());
    }

    public function test_unique_for_returns_3600(): void
    {
        $job = new GenerateRecurringInvoiceJob('any-id');

        $this->assertSame(3600, $job->uniqueFor());
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_due_active_template_creates_draft_invoice_and_advances_counters(): void
    {
        $ri = $this->createDueTemplate();

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $ri->refresh();
        $this->assertSame(1, $ri->occurrences_generated);
        $this->assertNotNull($ri->last_generated_at);
        $this->assertNotNull($ri->next_run_at);
        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);

        $invoice = Invoice::where('recurring_invoice_id', $ri->id)->firstOrFail();
        $this->assertSame(InvoiceStatusEnum::Draft, $invoice->status);
    }

    public function test_auto_issue_true_generates_issued_invoice(): void
    {
        $ri = $this->createDueTemplate(['auto_issue' => true]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::where('recurring_invoice_id', $ri->id)->firstOrFail();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->status);
        $this->assertNotNull($invoice->number);
    }

    public function test_tenant_default_state_issued_auto_issues_invoice(): void
    {
        $ri = $this->createDueTemplate(['auto_issue' => false]);

        // Set tenant interface to auto-issue
        TenantInterface::updateOrCreate(
            ['tenant_id' => $ri->tenant_id],
            ['recurring_default_state' => RecurringDefaultStateEnum::Issued],
        );

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::where('recurring_invoice_id', $ri->id)->firstOrFail();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->status);
    }

    public function test_invoice_is_linked_via_recurring_invoice_id(): void
    {
        $ri = $this->createDueTemplate();

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $invoice = Invoice::where('recurring_invoice_id', $ri->id)->firstOrFail();
        $this->assertSame($ri->id, $invoice->recurring_invoice_id);
    }

    // -------------------------------------------------------------------------
    // Failure / edge cases
    // -------------------------------------------------------------------------

    public function test_limit_reached_after_generation_marks_completed(): void
    {
        $ri = $this->createDueTemplate(['occurrences_limit' => 1, 'occurrences_generated' => 0]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Completed, $ri->status);
        $this->assertNull($ri->next_run_at);
        $this->assertSame(1, $ri->occurrences_generated);
    }

    public function test_next_run_beyond_end_date_marks_completed(): void
    {
        // next_run_at = today; after generation next would be +1 month; set end_date to 2 weeks from now
        $ri = $this->createDueTemplate([
            'end_date' => now()->addWeeks(2)->toDateString(),
            'next_run_at' => now()->toDateString(),
        ]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Completed, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_paused_template_at_handle_time_is_noop(): void
    {
        $ri = $this->createDueTemplate();
        $ri->update(['status' => RecurringInvoiceStatusEnum::Paused, 'next_run_at' => null]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $this->assertSame(0, Invoice::where('recurring_invoice_id', $ri->id)->count());
    }

    public function test_cancelled_template_at_handle_time_is_noop(): void
    {
        $ri = $this->createDueTemplate();
        $ri->update(['status' => RecurringInvoiceStatusEnum::Cancelled, 'next_run_at' => null]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $this->assertSame(0, Invoice::where('recurring_invoice_id', $ri->id)->count());
    }

    public function test_future_next_run_at_is_noop_idempotency(): void
    {
        $ri = $this->createDueTemplate(['next_run_at' => now()->addDays(5)->toDateString()]);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        $this->assertSame(0, Invoice::where('recurring_invoice_id', $ri->id)->count());
    }

    public function test_tenant_context_bound_correctly_for_cross_tenant_isolation(): void
    {
        // Create template for tenant A
        $userA = $this->actingAsTenantUser('Admin');
        $tenantAId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantAId,
            'type' => InvoiceTypeEnum::Monthly,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => 15,
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'next_run_at' => now()->toDateString(),
            'occurrences_generated' => 0,
            'customer_name' => 'Tenant A Client',
            'due_days' => 14,
        ]);

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantAId,
            'recurring_invoice_id' => $ri->id,
            'description' => 'Service',
            'quantity' => 1.0,
            'unit_price' => 200.0,
            'position' => 0,
        ]);

        // Clear tenant context (simulate running in a job outside HTTP)
        app()->offsetUnset('current_tenant_id');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        GenerateRecurringInvoiceJob::dispatchSync($ri->id);

        // Invoice must be created with correct tenant_id
        $invoice = Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $ri->id)->firstOrFail();
        $this->assertSame($tenantAId, $invoice->tenant_id);
    }
}
