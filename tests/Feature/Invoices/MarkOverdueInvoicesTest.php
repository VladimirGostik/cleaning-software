<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Events\InvoiceMarkedOverdue;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_past_due_becomes_overdue_and_dispatches_event(): void
    {
        Event::fake([InvoiceMarkedOverdue::class]);
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('overdue', $invoice->status->value);
        Event::assertDispatched(InvoiceMarkedOverdue::class, fn (InvoiceMarkedOverdue $e) => $e->invoiceId === $invoice->id && $e->tenantId === $tenant->id);
    }

    public function test_draft_invoice_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'due_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:mark-overdue-invoices');

        $invoice->refresh();
        $this->assertSame('draft', $invoice->status->value);
    }

    public function test_paid_invoice_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->paid()->create(['tenant_id' => $tenant->id, 'due_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:mark-overdue-invoices');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status->value);
    }

    public function test_cancelled_invoice_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'due_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:mark-overdue-invoices');

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status->value);
    }

    public function test_not_yet_due_invoice_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'due_date' => now()->addDay()->toDateString()]);

        $this->artisan('app:mark-overdue-invoices');

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status->value);
    }

    public function test_credit_note_never_marked_overdue(): void
    {
        $tenant = Tenant::factory()->create();
        $original = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $creditNote = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'credited_invoice_id' => $original->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('app:mark-overdue-invoices');

        $creditNote->refresh();
        $this->assertSame('issued', $creditNote->status->value);
    }

    public function test_running_twice_is_idempotent(): void
    {
        Event::fake([InvoiceMarkedOverdue::class]);
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'due_date' => now()->subDay()->toDateString()]);

        $this->artisan('app:mark-overdue-invoices');
        $this->artisan('app:mark-overdue-invoices');

        $invoice->refresh();
        $this->assertSame('overdue', $invoice->status->value);
        Event::assertDispatchedTimes(InvoiceMarkedOverdue::class, 1);
    }
}
