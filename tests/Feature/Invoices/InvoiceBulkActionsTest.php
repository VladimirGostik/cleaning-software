<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Enums\BulkSkipReasonEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    /** @param array<string, mixed> $attributes */
    private function make(string $state, array $attributes = []): Invoice
    {
        $factory = match ($state) {
            'issued' => Invoice::factory()->issued(),
            'overdue' => Invoice::factory()->overdue(),
            'paid' => Invoice::factory()->paid(),
            'cancelled' => Invoice::factory()->cancelled(),
            default => Invoice::factory(),
        };

        return $factory->create(['tenant_id' => $this->tenant->id, 'total' => '100.00', ...$attributes]);
    }

    /** @return array{action: string, processed: int, skipped: list<array{id: string, number: ?string, reason: string}>} */
    private function bulkResult(): array
    {
        /** @var array{action: string, processed: int, skipped: list<array{id: string, number: ?string, reason: string}>} */
        return session('bulk_result');
    }

    /**
     * @param  list<array{id: string, number: ?string, reason: string}>  $skipped
     * @return array<string, string>
     */
    private function reasonsById(array $skipped): array
    {
        $map = [];

        foreach ($skipped as $row) {
            $map[$row['id']] = $row['reason'];
        }

        return $map;
    }

    public function test_bulk_send_processes_only_issued_with_email_and_reports_the_rest(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $ok = $this->make('issued', ['customer_email' => 'a@example.com']);
        $overdue = $this->make('overdue', ['customer_email' => 'b@example.com']);
        $noEmail = $this->make('issued', ['customer_email' => null]);
        $draft = $this->make('draft', ['customer_email' => 'c@example.com']);
        $paid = $this->make('paid', ['customer_email' => 'd@example.com']);
        $other = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $missing = (string) fake()->uuid();

        $ids = [$ok->id, $overdue->id, $noEmail->id, $draft->id, $paid->id, $other->id, $missing];

        $this->from(route('invoices.index'))->post(route('invoices.bulk.send'), ['ids' => $ids])->assertRedirect(route('invoices.index'));

        $result = $this->bulkResult();
        $this->assertSame('send', $result['action']);
        $this->assertSame(1, $result['processed']);
        $this->assertSame([
            $overdue->id => BulkSkipReasonEnum::NotIssued->value,
            $noEmail->id => BulkSkipReasonEnum::NoEmail->value,
            $draft->id => BulkSkipReasonEnum::NotIssued->value,
            $paid->id => BulkSkipReasonEnum::NotIssued->value,
            $other->id => BulkSkipReasonEnum::NotFound->value,
            $missing => BulkSkipReasonEnum::NotFound->value,
        ], $this->reasonsById($result['skipped']));
        Notification::assertSentOnDemandTimes(InvoiceIssued::class, 1);
    }

    public function test_bulk_pay_records_full_balance_with_shared_date_and_own_payment_type(): void
    {
        $user = $this->actingAsTenantUser('Admin', $this->tenant);
        $issued = $this->make('issued', ['payment_type' => 'cash']);
        $partial = $this->make('overdue');
        $partial->forceFill(['paid_amount' => '30.00'])->saveQuietly();
        InvoicePayment::factory()->create(['tenant_id' => $this->tenant->id, 'invoice_id' => $partial->id, 'amount' => '30.00']);
        $draft = $this->make('draft');
        $paid = $this->make('paid');
        $cancelled = $this->make('cancelled');
        $other = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $date = now()->subDays(2)->toDateString();

        $this->from(route('invoices.index'))->post(route('invoices.bulk.pay'), [
            'ids' => [$issued->id, $partial->id, $draft->id, $paid->id, $cancelled->id, $other->id],
            'paid_on' => $date,
        ])->assertRedirect(route('invoices.index'));

        $result = $this->bulkResult();
        $this->assertSame(2, $result['processed']);
        $this->assertSame([
            $draft->id => 'not_payable',
            $paid->id => 'not_payable',
            $cancelled->id => 'not_payable',
            $other->id => 'not_found',
        ], $this->reasonsById($result['skipped']));

        $this->assertSame(InvoiceStatusEnum::Paid, $issued->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Paid, $partial->refresh()->status);

        $payment = InvoicePayment::query()->where('invoice_id', $issued->id)->sole();
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame($date, $payment->paid_on->toDateString());
        $this->assertSame('cash', $payment->payment_type->value);
        $this->assertSame($user->id, $payment->recorded_by_user_id);

        $this->assertSame('70.00', InvoicePayment::query()->where('invoice_id', $partial->id)->latest('created_at')->firstOrFail()->amount);
    }

    public function test_bulk_pay_refunds_credit_note_in_full_and_skips_zero_balance(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $original = $this->make('cancelled');
        $note = $this->make('issued', ['total' => '-100.00', 'credited_invoice_id' => $original->id]);
        $zero = $this->make('issued', ['total' => '100.00', 'paid_amount' => '100.00']);

        $this->from(route('invoices.index'))->post(route('invoices.bulk.pay'), [
            'ids' => [$note->id, $zero->id],
            'paid_on' => now()->toDateString(),
        ]);

        $result = $this->bulkResult();
        $this->assertSame(1, $result['processed']);
        $this->assertSame([$zero->id => 'not_payable'], $this->reasonsById($result['skipped']));
        $this->assertSame(InvoiceStatusEnum::Paid, $note->refresh()->status);
        $this->assertSame('-100.00', InvoicePayment::query()->where('invoice_id', $note->id)->sole()->amount);
    }

    public function test_bulk_pay_requires_paid_on_not_in_future(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->make('issued');

        $this->post(route('invoices.bulk.pay'), ['ids' => [$invoice->id]])->assertSessionHasErrors('paid_on');
        $this->post(route('invoices.bulk.pay'), ['ids' => [$invoice->id], 'paid_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_on');
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
    }

    public function test_bulk_issue_processes_drafts_only(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $draft1 = $this->make('draft');
        $draft2 = $this->make('draft');
        $issued = $this->make('issued');

        $this->from(route('invoices.index'))->post(route('invoices.bulk.issue'), ['ids' => [$draft1->id, $issued->id, $draft2->id]]);

        $result = $this->bulkResult();
        $this->assertSame(2, $result['processed']);
        $this->assertSame([$issued->id => 'not_draft'], $this->reasonsById($result['skipped']));
        $this->assertSame(InvoiceStatusEnum::Issued, $draft1->refresh()->status);
        $this->assertNotNull($draft1->number);
        $this->assertNotNull($draft2->refresh()->number);
        $this->assertNotSame($draft1->number, $draft2->number);
    }

    public function test_bulk_issue_with_incomplete_supplier_skips_all_and_consumes_no_number(): void
    {
        $this->tenant->update(['address_line' => null]);
        $this->actingAsTenantUser('Admin', $this->tenant);
        $drafts = [$this->make('draft'), $this->make('draft')];

        $this->from(route('invoices.index'))->post(route('invoices.bulk.issue'), ['ids' => array_map(fn (Invoice $i): string => $i->id, $drafts)]);

        $result = $this->bulkResult();
        $this->assertSame(0, $result['processed']);
        $this->assertCount(2, $result['skipped']);
        $this->assertSame(['supplier_incomplete'], array_values(array_unique(array_column($result['skipped'], 'reason'))));

        foreach ($drafts as $draft) {
            $draft->refresh();
            $this->assertSame(InvoiceStatusEnum::Draft, $draft->status);
            $this->assertNull($draft->number);
        }
    }

    public function test_ids_validation(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->make('issued');

        $this->post(route('invoices.bulk.send'), ['ids' => []])->assertSessionHasErrors('ids');
        $this->post(route('invoices.bulk.send'), [])->assertSessionHasErrors('ids');
        $this->post(route('invoices.bulk.send'), ['ids' => ['not-a-uuid']])->assertSessionHasErrors('ids.0');
        $this->post(route('invoices.bulk.send'), ['ids' => [$invoice->id, $invoice->id]])->assertSessionHasErrors();

        $tooMany = array_map(fn (): string => (string) fake()->uuid(), range(1, config()->integer('invoicing.bulk.max_ids') + 1));
        $this->post(route('invoices.bulk.send'), ['ids' => $tooMany])->assertSessionHasErrors('ids');
    }

    public function test_user_without_edit_permission_is_forbidden_on_bulk_writes(): void
    {
        $this->actingAsTenantUser('Interná upratovačka', $this->tenant);
        $invoice = $this->make('issued');

        $this->post(route('invoices.bulk.send'), ['ids' => [$invoice->id]])->assertForbidden();
        $this->post(route('invoices.bulk.issue'), ['ids' => [$invoice->id]])->assertForbidden();
        $this->post(route('invoices.bulk.pay'), ['ids' => [$invoice->id], 'paid_on' => now()->toDateString()])->assertForbidden();
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);
    }

    public function test_bulk_pdf_is_forbidden_without_view_permission(): void
    {
        $this->actingAsTenantUser('Interná upratovačka', $this->tenant);
        $invoice = $this->make('issued');

        $this->get(route('invoices.bulk.pdf', ['ids' => [$invoice->id]]))->assertForbidden();
    }

    public function test_flash_shares_bulk_result_prop(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->make('issued', ['customer_email' => null]);
        Notification::fake();
        $this->mock(RendersInvoicePdf::class);

        $this->post(route('invoices.bulk.send'), ['ids' => [$invoice->id]]);

        $this->get(route('invoices.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('flash.bulk_result.action', 'send')
            ->where('flash.bulk_result.processed', 0)
            ->where('flash.bulk_result.skipped.0.reason', 'no_email'));
    }
}
