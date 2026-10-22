<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Data\Invoices\InvoicePaymentData;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Pdf\PayBySquareService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'paid_on' => now()->toDateString(),
            'amount' => 40,
            'payment_type' => PaymentTypeEnum::Transfer->value,
            'note' => null,
        ], $overrides);
    }

    public function test_partial_payment_keeps_status_and_sets_paid_amount(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload())->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->status);
        $this->assertSame('40.00', $invoice->paid_amount);
        $this->assertTrue($invoice->isPartiallyPaid());
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'recorded_by_user_id' => $user->id, 'source' => 'manual']);
    }

    public function test_second_payment_settling_balance_marks_paid_with_payment_date(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $date = now()->subDays(2)->toDateString();

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 60, 'paid_on' => $date]))->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertSame($date, $invoice->paid_at?->toDateString());
        $this->assertFalse($invoice->isPartiallyPaid());
        $this->assertSame(0.0, $invoice->balance_due);
    }

    public function test_deposit_reduces_balance_and_exact_amount_settles(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->withDeposit(30)->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 70]))->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);
    }

    public function test_overdue_invoice_accepts_payment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 100]))->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);
    }

    public function test_two_payments_same_day_accumulate(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 10]));
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 15.5]));

        $this->assertSame('25.50', $invoice->refresh()->paid_amount);
        $this->assertSame(2, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_overpayment_is_rejected_on_amount(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 100.01]))->assertSessionHasErrors('amount');

        $this->assertSame('0.00', $invoice->refresh()->paid_amount);
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_future_date_zero_amount_and_bad_type_fail_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload(['paid_on' => now()->addDay()->toDateString()]))->assertSessionHasErrors('paid_on');
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 0]))->assertSessionHasErrors('amount');
        $this->post(route('invoices.pay', $invoice), $this->payload(['payment_type' => 'bitcoin']))->assertSessionHasErrors('payment_type');
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_draft_paid_and_cancelled_invoices_reject_payment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        foreach ([Invoice::factory()->create(['tenant_id' => $tenant->id]), Invoice::factory()->paid()->create(['tenant_id' => $tenant->id]), Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id])] as $invoice) {
            $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 1]))->assertSessionHasErrors('status');
        }

        $this->assertSame(1, InvoicePayment::query()->count());
    }

    public function test_user_without_edit_permission_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->post(route('invoices.pay', $invoice), $this->payload())->assertForbidden();
    }

    public function test_deleting_settling_payment_reverts_to_issued(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00', 'due_date' => now()->addDays(5)->toDateString()]);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 60]));
        $settling = InvoicePayment::query()->where('amount', '60.00')->firstOrFail();

        $this->delete(route('invoices.payments.destroy', [$invoice, $settling]))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame('40.00', $invoice->paid_amount);
        $this->assertSoftDeleted('invoice_payments', ['id' => $settling->id]);
    }

    public function test_deleting_settling_payment_of_past_due_invoice_reverts_to_overdue(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 100]));
        $payment = InvoicePayment::query()->firstOrFail();

        $this->delete(route('invoices.payments.destroy', [$invoice, $payment]));

        $this->assertSame(InvoiceStatusEnum::Overdue, $invoice->refresh()->status);
    }

    public function test_deleting_middle_payment_keeps_paid_when_remaining_still_settle(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 50]));
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 50]));
        $middle = InvoicePayment::query()->first();
        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);

        // pretend a legacy extra row exists so remaining still settle: add third payment directly
        InvoicePayment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'amount' => '50.00']);
        $invoice->update(['paid_amount' => '150.00']);

        $this->delete(route('invoices.payments.destroy', [$invoice, $middle]))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertSame('100.00', $invoice->paid_amount);
    }

    public function test_cross_invoice_and_cross_tenant_payment_delete_is_not_found(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $otherInvoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $payment = InvoicePayment::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $otherInvoice->id]);

        $this->delete(route('invoices.payments.destroy', [$invoice, $payment]))->assertNotFound();

        $other = Tenant::factory()->create();
        $foreignInvoice = Invoice::factory()->issued()->create(['tenant_id' => $other->id]);
        $foreignPayment = InvoicePayment::factory()->create(['tenant_id' => $other->id, 'invoice_id' => $foreignInvoice->id]);

        $this->delete(route('invoices.payments.destroy', [$foreignInvoice, $foreignPayment]))->assertNotFound();
        $this->assertNotSoftDeleted('invoice_payments', ['id' => $foreignPayment->id]);
    }

    public function test_delete_payment_on_cancelled_invoice_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));
        $payment = InvoicePayment::query()->firstOrFail();
        $invoice->update(['status' => InvoiceStatusEnum::Cancelled]);

        $this->delete(route('invoices.payments.destroy', [$invoice, $payment]))->assertSessionHasErrors('status');
        $this->assertNotSoftDeleted('invoice_payments', ['id' => $payment->id]);
    }

    public function test_cancelling_partially_paid_invoice_keeps_payments(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));

        app(InvoiceService::class)->cancel($invoice->refresh(), $user);

        $this->assertSame(InvoiceStatusEnum::Cancelled, $invoice->refresh()->status);
        $this->assertSame(1, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertNotNull($invoice->creditNote);
    }

    public function test_show_page_exposes_payments_and_partial_flag(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));

        $this->get(route('invoices.show', $invoice))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.is_partially_paid', true)
                ->where('invoice.paid_amount', '40.00')
                ->where('invoice.balance_due', '60')
                ->has('invoice.payments', 1)
                ->where('invoice.payments.0.amount', '40.00'),
        );
    }

    public function test_stats_pending_sum_uses_open_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 40]));

        $stats = app(InvoiceService::class)->stats();

        $this->assertSame(1, $stats->pending->count);
        $this->assertSame('60.00', $stats->pending->amount);
    }

    public function test_paid_factory_invoice_is_consistent_with_zero_balance(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->paid()->withDeposit(30)->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $invoice->refresh();
        $this->assertSame('70.00', $invoice->paid_amount);
        $this->assertSame(0.0, $invoice->balance_due);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_migration_backfills_paid_invoices(): void
    {
        $tenant = Tenant::factory()->create();
        $paid = Invoice::factory()->paid()->create(['tenant_id' => $tenant->id, 'total' => '80.00']);
        $open = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '50.00']);
        InvoicePayment::query()->delete();
        Invoice::query()->update(['paid_amount' => 0]);

        $migration = require database_path('migrations/2026_10_12_000200_add_paid_amount_to_invoices_table.php');
        $this->assertTrue($migration instanceof Migration && method_exists($migration, 'up'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
        $migration->up();

        $this->assertSame('80.00', $paid->refresh()->paid_amount);
        $this->assertSame('0.00', $open->refresh()->paid_amount);
        $this->assertSame(1, InvoicePayment::query()->where('invoice_id', $paid->id)->count());
        $this->assertSame(0, InvoicePayment::query()->where('invoice_id', $open->id)->count());
    }

    private function creditNote(Tenant $tenant, string $total = '-100.00'): Invoice
    {
        $original = Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        return Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'total' => $total,
            'credited_invoice_id' => $original->id,
            'due_date' => now()->toDateString(),
        ]);
    }

    public function test_credit_note_partial_and_settling_refund_are_stored_negative(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $note = $this->creditNote($tenant);

        $this->post(route('invoices.pay', $note), $this->payload(['amount' => 40]))->assertSessionHasNoErrors();

        $note->refresh();
        $this->assertSame(InvoiceStatusEnum::Issued, $note->status);
        $this->assertSame('-40.00', $note->paid_amount);
        $this->assertTrue($note->isPartiallyPaid());
        $this->assertSame(-60.0, $note->balance_due);
        $this->assertSame('-40.00', InvoicePayment::query()->where('invoice_id', $note->id)->sole()->amount);

        $this->post(route('invoices.pay', $note), $this->payload(['amount' => 60]))->assertSessionHasNoErrors();

        $note->refresh();
        $this->assertSame(InvoiceStatusEnum::Paid, $note->status);
        $this->assertSame('-100.00', $note->paid_amount);
        $this->assertFalse($note->isPartiallyPaid());
    }

    public function test_credit_note_over_refund_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $note = $this->creditNote($tenant);

        $this->post(route('invoices.pay', $note), $this->payload(['amount' => 100.01]))->assertSessionHasErrors('amount');

        $this->assertSame('0.00', $note->refresh()->paid_amount);
    }

    public function test_deleting_refund_reopens_credit_note(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $note = $this->creditNote($tenant);
        $this->post(route('invoices.pay', $note), $this->payload(['amount' => 100]));
        $payment = InvoicePayment::query()->where('invoice_id', $note->id)->sole();

        $this->delete(route('invoices.payments.destroy', [$note, $payment]))->assertRedirect();

        $this->assertSame(InvoiceStatusEnum::Issued, $note->refresh()->status);
        $this->assertSame('0.00', $note->paid_amount);
    }

    public function test_show_page_lists_signed_amount_and_credit_note_flag(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $note = $this->creditNote($tenant);
        $this->post(route('invoices.pay', $note), $this->payload(['amount' => 25]));

        $this->get(route('invoices.show', $note))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('invoice.is_credit_note', true)
                ->where('invoice.payments.0.amount', '-25.00')
                ->where('invoice.is_partially_paid', true),
        );
    }

    public function test_service_rejects_payment_when_invoice_was_cancelled_concurrently(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $stale = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);
        Invoice::query()->whereKey($stale->id)->update(['status' => InvoiceStatusEnum::Cancelled]);

        try {
            app(InvoiceService::class)->recordPayment($stale, new InvoicePaymentData(now()->toDateString(), 10.0, PaymentTypeEnum::Cash), null);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_service_rejects_non_positive_amount(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00']);

        $this->expectException(ValidationException::class);

        app(InvoiceService::class)->recordPayment($invoice, new InvoicePaymentData(now()->toDateString(), 0.0, PaymentTypeEnum::Cash), null);
    }

    public function test_pdf_view_and_qr_amount_do_not_change_with_partial_payment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->withDeposit(40)->create([
            'tenant_id' => $tenant->id,
            'total' => '100.00',
            'supplier_iban' => 'SK8975000000000123456789',
            'supplier_name' => 'Test s.r.o.',
        ]);
        $render = function (Invoice $i): string {
            app()->setLocale('sk');

            return view('pdf.invoices.classic', ['invoice' => $i->load('items'), 'qrDataUri' => null, 'signatureDataUri' => null])->render();
        };
        $qr = app(PayBySquareService::class);

        $htmlBefore = $render($invoice);
        $qrBefore = $qr->dataUri($invoice);
        $this->assertNotNull($qrBefore);
        $this->assertSame(60.0, $invoice->amount_payable);

        $this->post(route('invoices.pay', $invoice), $this->payload(['amount' => 20]))->assertSessionHasNoErrors();
        $invoice = $invoice->fresh();

        $this->assertNotNull($invoice);
        $this->assertSame(40.0, $invoice->balance_due);
        $this->assertSame(60.0, $invoice->amount_payable);
        $this->assertSame($htmlBefore, $render($invoice));
        $this->assertSame($qrBefore, $qr->dataUri($invoice));
    }
}
