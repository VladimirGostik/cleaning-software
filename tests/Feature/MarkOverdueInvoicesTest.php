<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_issued_past_due_invoices_are_marked_overdue(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $pastDue = Invoice::factory()->create([
            'tenant_id' => $tenantA->id,
            'status' => InvoiceStatusEnum::Issued->value,
            'number' => 'FA-TEST-0001',
            'due_date' => now()->subDays(3)->toDateString(),
            'issued_at' => now()->subDays(10),
            'customer_name' => 'Test A',
            'supplier_name' => 'Supplier A',
        ]);

        $pastDueTenantB = Invoice::factory()->create([
            'tenant_id' => $tenantB->id,
            'status' => InvoiceStatusEnum::Issued->value,
            'number' => 'FA-TEST-0002',
            'due_date' => now()->subDay()->toDateString(),
            'issued_at' => now()->subDays(5),
            'customer_name' => 'Test B',
            'supplier_name' => 'Supplier B',
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

        $this->assertEquals(InvoiceStatusEnum::Overdue, $pastDue->fresh()->status);
        $this->assertEquals(InvoiceStatusEnum::Overdue, $pastDueTenantB->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Skips non-eligible statuses
    // -------------------------------------------------------------------------

    public function test_draft_paid_cancelled_invoices_are_not_affected(): void
    {
        $tenant = Tenant::factory()->create();

        $draft = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Draft->value,
            'due_date' => now()->subDays(5)->toDateString(),
            'customer_name' => 'Draft Corp',
            'supplier_name' => 'Supplier',
        ]);

        $paid = Invoice::factory()->paid()->create([
            'tenant_id' => $tenant->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $cancelled = Invoice::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

        $this->assertEquals(InvoiceStatusEnum::Draft, $draft->fresh()->status);
        $this->assertEquals(InvoiceStatusEnum::Paid, $paid->fresh()->status);
        $this->assertEquals(InvoiceStatusEnum::Cancelled, $cancelled->fresh()->status);
    }

    public function test_issued_not_yet_due_is_not_affected(): void
    {
        $tenant = Tenant::factory()->create();

        $notYetDue = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued->value,
            'number' => 'FA-TEST-FUTURE',
            'due_date' => now()->addDays(5)->toDateString(),
            'issued_at' => now()->subDay(),
            'customer_name' => 'Future Corp',
            'supplier_name' => 'Supplier',
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

        $this->assertEquals(InvoiceStatusEnum::Issued, $notYetDue->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Credit notes must never become Overdue (BUG 3)
    // -------------------------------------------------------------------------

    public function test_credit_note_with_past_due_date_is_not_marked_overdue(): void
    {
        $tenant = Tenant::factory()->create();

        $original = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'number' => 'FA-ORIG-001',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        // Credit note: Issued status, due_date = yesterday, credited_invoice_id set
        $creditNote = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'credited_invoice_id' => $original->id,
            'due_date' => now()->subDay()->toDateString(),
            'number' => 'CN-ORIG-001',
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

        $this->assertEquals(InvoiceStatusEnum::Issued, $creditNote->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_running_command_twice_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued->value,
            'number' => 'FA-TEST-IDEM',
            'due_date' => now()->subDays(2)->toDateString(),
            'issued_at' => now()->subDays(5),
            'customer_name' => 'Idempotent Corp',
            'supplier_name' => 'Supplier',
        ]);

        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();
        $this->artisan('app:mark-overdue-invoices')->assertSuccessful();

        $this->assertEquals(1, Invoice::withoutGlobalScopes()->where('status', InvoiceStatusEnum::Overdue->value)->count());
    }
}
