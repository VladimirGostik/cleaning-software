<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use App\Events\InvoiceMarkedOverdue;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CreatesCamtStatements;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

/**
 * @phpstan-type Row array{reference: string, file_index: int, date: string, amount: string, payer_name: string|null, payer_iban: string|null, variable_symbol: string|null, note: string|null, state: string, invoice: array{id: string, number: string}|null, balance_before: string|null, amount_to_record: string|null, excess: string|null, instalment_position: int|null, instalment_count: int|null, existing_payment_id: string|null, include: bool}
 * @phpstan-type Link array{id: string, number: string, balance_due: string}
 * @phpstan-type Result array{recorded_count: int, recorded_total: string, already_imported: int, excluded: int, settled_invoices: list<Link>, partly_paid_invoices: list<Link>, overpayments: list<array{reference: string, invoice_id: string, invoice_number: string, payer_name: string|null, excess: string}>, skipped: list<array{reference: string, state: string, invoice_number: string|null}>}
 */
final class InvoicePaymentImportTest extends TestCase
{
    use CreatesCamtStatements;
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    private const array JSON = ['Accept' => 'application/json'];

    private const string UNKNOWN_IBAN = 'SK9999000000009999999999';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExpenses();
        $this->alpha->update(['iban' => self::STATEMENT_IBAN]);
        $this->beta->update(['iban' => self::OTHER_IBAN]);
    }

    /** @return array<string, mixed> */
    private function paying(string $reference, ?string $vs, string $amount, string $date = '2026-10-05', string $payer = 'Platca A'): array
    {
        return $this->credit($reference, $amount, $date, [
            'counterparty' => null,
            'payer' => $payer,
            'payer_iban' => 'SK3333000000003333333333',
            'end_to_end' => $vs === null ? 'NOTPROVIDED' : '/VS'.str_pad($vs, 10, '0', STR_PAD_LEFT).'/SS0000000000',
        ]);
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function file(array $entries, string $iban = self::STATEMENT_IBAN, string $name = 'vypis.xml'): UploadedFile
    {
        return $this->camtFile($this->camtXml($entries, $iban, period: ['2026-10-01T00:00:00.000+02:00', '2026-10-31T23:59:59.000+01:00']), $name);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return TestResponse<Response>
     */
    private function preview(array $files, ?User $user = null, ?Tenant $tenant = null): TestResponse
    {
        return $this->webAs($user ?? $this->accountant, $tenant ?? $this->alpha)
            ->post('/invoices/payment-import/preview', ['files' => $files], self::JSON);
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<array<string, mixed>>  $rows
     * @return TestResponse<Response>
     */
    private function import(array $files, array $rows): TestResponse
    {
        return $this->webAs($this->accountant, $this->alpha)->post('/invoices/payment-import', ['files' => $files, 'rows' => $rows]);
    }

    /** @return array{reference: string, include: string, invoice_id: string} */
    private function tick(string $reference, bool $include = true, string $invoiceId = ''): array
    {
        return ['reference' => $reference, 'include' => $include ? '1' : '0', 'invoice_id' => $invoiceId];
    }

    /** @return Result */
    private function importResult(): array
    {
        /** @var Result */
        return session('result');
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array<string, mixed>>
     */
    private function jsonList(TestResponse $response, ?string $key = null): array
    {
        /** @var list<array<string, mixed>> */
        return $key === null ? $response->json() : $response->json($key);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<string, list<string>>
     */
    private function errors(TestResponse $response): array
    {
        /** @var array<string, list<string>> */
        return $response->json('errors');
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return Row
     */
    private function row(TestResponse $response, string $reference): array
    {
        /** @var list<Row> $rows */
        $rows = $response->json('rows');

        foreach ($rows as $row) {
            if ($row['reference'] === $reference) {
                return $row;
            }
        }

        $this->fail('Row '.$reference.' missing from the preview.');
    }

    /** @param  array<string, mixed>  $overrides */
    private function invoice(string $vs, string $total = '100.00', string $state = 'issued', array $overrides = [], ?Tenant $tenant = null): Invoice
    {
        $factory = match ($state) {
            'draft' => Invoice::factory(),
            'paid' => Invoice::factory()->paid(),
            'overdue' => Invoice::factory()->overdue(),
            'cancelled' => Invoice::factory()->cancelled(),
            default => Invoice::factory()->issued(),
        };

        return $factory->create([
            'tenant_id' => ($tenant ?? $this->alpha)->id,
            'variable_symbol' => $vs,
            'number' => 'FA-'.$vs,
            'total' => $total,
            'due_date' => '2026-10-20',
            ...$overrides,
        ]);
    }

    private function manualPayment(Invoice $invoice, string $amount, string $paidOn, ?string $reference = null): InvoicePayment
    {
        $payment = InvoicePayment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'paid_on' => $paidOn,
            'payment_type' => PaymentTypeEnum::Transfer,
            'source' => PaymentSourceEnum::Manual,
            'external_reference' => $reference,
        ]);

        $invoice->forceFill(['paid_amount' => number_format((float) $invoice->paid_amount + (float) $amount, 2, '.', '')])->saveQuietly();

        return $payment;
    }

    private function key(string $reference, string $iban = self::STATEMENT_IBAN): string
    {
        return hash('sha256', 'camt053|'.$iban.'|'.$reference);
    }

    public function test_exact_payment_of_an_overdue_invoice_is_matched_and_settles_it(): void
    {
        $invoice = $this->invoice('2026001', '120.00', 'overdue');

        $preview = $this->preview([$this->file([$this->paying('R1', '2026001', '120.00')])])->assertOk();

        $row = $this->row($preview, 'R1');
        $this->assertSame('matched', $row['state']);
        $this->assertTrue($row['include']);
        $this->assertSame($invoice->id, $row['invoice']['id'] ?? null);
        $this->assertSame('120.00', $row['amount_to_record']);
        $this->assertSame('0.00', $row['excess']);
        $this->assertSame('Platca A', $row['payer_name']);
        $this->assertSame('SK3333000000003333333333', $row['payer_iban']);
        $this->assertSame('2026001', $row['variable_symbol']);
        $this->assertSame(0, $preview->json('files.0.index'));
        $this->assertSame('2026-10-01', $preview->json('files.0.period_from'));
        $this->assertSame(1, $preview->json('files.0.credit_rows'));

        $this->import([$this->file([$this->paying('R1', '2026001', '120.00')])], [$this->tick('R1')])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.payment-import'));

        $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();
        $this->assertSame(PaymentSourceEnum::BankImport, $payment->source);
        $this->assertSame(PaymentTypeEnum::Transfer, $payment->payment_type);
        $this->assertSame('2026-10-05', $payment->paid_on->toDateString());
        $this->assertSame($this->key('R1'), $payment->external_reference);
        $this->assertSame($this->accountant->id, $payment->recorded_by_user_id);
        $this->assertStringContainsString('R1', (string) $payment->note);

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertSame('2026-10-05', $invoice->paid_at?->toDateString());

        $result = $this->importResult();
        $this->assertSame(1, $result['recorded_count']);
        $this->assertSame('120.00', $result['recorded_total']);
        $this->assertSame($invoice->id, $result['settled_invoices'][0]['id']);
        $this->assertSame([], $result['partly_paid_invoices']);
    }

    public function test_three_instalments_in_one_upload_settle_the_invoice(): void
    {
        $invoice = $this->invoice('2026002', '100.00');
        $entries = [
            $this->paying('R1', '2026002', '30.00', '2026-10-02'),
            $this->paying('R2', '2026002', '30.00', '2026-10-03'),
            $this->paying('R3', '2026002', '40.00', '2026-10-04'),
        ];

        $preview = $this->preview([$this->file($entries)])->assertOk();

        $this->assertSame(['partial', 'partial', 'matched'], array_map(fn (string $ref): string => $this->row($preview, $ref)['state'], ['R1', 'R2', 'R3']));
        $this->assertSame([1, 2, 3], array_map(fn (string $ref): ?int => $this->row($preview, $ref)['instalment_position'], ['R1', 'R2', 'R3']));
        $this->assertSame(3, $this->row($preview, 'R1')['instalment_count']);
        $this->assertSame('100.00', $this->row($preview, 'R1')['balance_before']);
        $this->assertSame('40.00', $this->row($preview, 'R3')['balance_before']);

        $this->import([$this->file($entries)], [$this->tick('R1'), $this->tick('R2'), $this->tick('R3')])->assertSessionHasNoErrors();

        $this->assertSame(3, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);
    }

    public function test_instalment_across_two_imports_is_partial_then_matched(): void
    {
        $invoice = $this->invoice('2026003', '100.00');
        $first = [$this->paying('R1', '2026003', '60.00', '2026-10-02')];
        $second = [$this->paying('R2', '2026003', '40.00', '2026-10-09')];

        $this->import([$this->file($first)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $result = $this->importResult();
        $this->assertSame($invoice->id, $result['partly_paid_invoices'][0]['id']);
        $this->assertSame('40.00', $result['partly_paid_invoices'][0]['balance_due']);
        $this->assertSame([], $result['settled_invoices']);
        $this->assertSame(InvoiceStatusEnum::Issued, $invoice->refresh()->status);

        $preview = $this->preview([$this->file($second)])->assertOk();
        $this->assertSame('matched', $this->row($preview, 'R2')['state']);
        $this->assertNull($this->row($preview, 'R2')['instalment_position']);

        $this->import([$this->file($second)], [$this->tick('R2')])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);
    }

    public function test_overpayment_starts_unticked_and_records_the_open_amount_with_the_excess_in_the_note(): void
    {
        $invoice = $this->invoice('2026004', '100.00');
        $entries = [$this->paying('R1', '2026004', '130.00')];

        $row = $this->row($this->preview([$this->file($entries)])->assertOk(), 'R1');
        $this->assertSame('overpaid', $row['state']);
        $this->assertFalse($row['include']);
        $this->assertSame('100.00', $row['amount_to_record']);
        $this->assertSame('30.00', $row['excess']);

        $this->import([$this->file($entries)], [$this->tick('R1', false)])->assertSessionHasNoErrors();
        $this->assertSame(0, InvoicePayment::query()->count());
        $this->assertSame(1, $this->importResult()['excluded']);

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $payment = InvoicePayment::query()->sole();
        $this->assertSame('100.00', $payment->amount);
        $this->assertStringContainsString('30.00', (string) $payment->note);
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);

        $result = $this->importResult();
        $this->assertSame('R1', $result['overpayments'][0]['reference']);
        $this->assertSame('30.00', $result['overpayments'][0]['excess']);
        $this->assertSame('FA-2026004', $result['overpayments'][0]['invoice_number']);
    }

    public function test_underpayment_is_partial_and_the_invoice_stays_open(): void
    {
        $invoice = $this->invoice('2026005', '100.00', 'overdue');
        $entries = [$this->paying('R1', '2026005', '45.50')];

        $row = $this->row($this->preview([$this->file($entries)])->assertOk(), 'R1');
        $this->assertSame('partial', $row['state']);
        $this->assertTrue($row['include']);

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatusEnum::Overdue, $invoice->status);
        $this->assertSame(54.5, $invoice->balance_due);
    }

    public function test_a_legacy_imported_open_invoice_matches_like_any_other(): void
    {
        $this->invoice('2026006', '80.00', 'issued', ['legacy_id' => 4711]);

        $row = $this->row($this->preview([$this->file([$this->paying('R1', '2026006', '80.00')])])->assertOk(), 'R1');

        $this->assertSame('matched', $row['state']);
    }

    public function test_a_paid_invoice_is_never_recorded_without_a_manual_pick(): void
    {
        $invoice = $this->invoice('2026007', '50.00', 'paid');
        $entries = [$this->paying('R1', '2026007', '50.00')];

        $row = $this->row($this->preview([$this->file($entries)])->assertOk(), 'R1');
        $this->assertSame('invoice_paid', $row['state']);
        $this->assertFalse($row['include']);
        $this->assertNull($row['amount_to_record']);

        $before = InvoicePayment::query()->where('invoice_id', $invoice->id)->count();
        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $this->assertSame($before, InvoicePayment::query()->where('invoice_id', $invoice->id)->count());
        $result = $this->importResult();
        $this->assertSame(0, $result['recorded_count']);
        $this->assertSame('invoice_paid', $result['skipped'][0]['state']);
        $this->assertSame('FA-2026007', $result['skipped'][0]['invoice_number']);
    }

    public function test_cancelled_invoices_and_credit_notes_are_not_payable(): void
    {
        $this->invoice('2026008', '50.00', 'cancelled');
        $this->invoice('2026009', '-50.00', 'issued');

        $preview = $this->preview([$this->file([
            $this->paying('R1', '2026008', '50.00'),
            $this->paying('R2', '2026009', '50.00'),
        ])])->assertOk();

        $this->assertSame('invoice_not_payable', $this->row($preview, 'R1')['state']);
        $this->assertSame('invoice_not_payable', $this->row($preview, 'R2')['state']);
        $this->assertFalse($this->row($preview, 'R1')['include']);
    }

    public function test_unknown_and_missing_variable_symbols_can_be_assigned_by_hand(): void
    {
        $invoice = $this->invoice('2026010', '70.00');
        $draft = $this->invoice('2026011', '70.00', 'draft', ['number' => null]);
        $entries = [
            $this->paying('R1', '7777777', '70.00'),
            $this->paying('R2', null, '70.00'),
            $this->paying('R3', '2026011', '70.00'),
        ];

        $preview = $this->preview([$this->file($entries)])->assertOk();

        $this->assertSame('vs_not_found', $this->row($preview, 'R1')['state']);
        $this->assertSame('no_vs', $this->row($preview, 'R2')['state']);
        $this->assertSame('vs_not_found', $this->row($preview, 'R3')['state']);
        $this->assertNull($this->row($preview, 'R1')['invoice']);
        $this->assertFalse($this->row($preview, 'R2')['include']);

        $this->import([$this->file($entries)], [
            $this->tick('R1', true, $invoice->id),
            $this->tick('R2'),
            $this->tick('R3', true, $draft->id),
        ])->assertSessionHasNoErrors();

        $payment = InvoicePayment::query()->sole();
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame($this->key('R1'), $payment->external_reference);

        $skipped = collect($this->importResult()['skipped'])->pluck('state', 'reference')->all();
        $this->assertSame(['R2' => 'no_vs', 'R3' => 'invoice_not_payable'], $skipped);
    }

    public function test_a_manual_pick_overrides_the_variable_symbol(): void
    {
        $matching = $this->invoice('2026012', '40.00');
        $picked = $this->invoice('2026013', '40.00');

        $this->import([$this->file([$this->paying('R1', '2026012', '40.00')])], [$this->tick('R1', true, $picked->id)])->assertSessionHasNoErrors();

        $this->assertSame($picked->id, InvoicePayment::query()->sole()->invoice_id);
        $this->assertSame(InvoiceStatusEnum::Issued, $matching->refresh()->status);
    }

    public function test_an_invoice_of_another_firm_is_never_recorded(): void
    {
        $foreign = $this->invoice('2026014', '40.00', 'issued', [], $this->beta);

        $this->import([$this->file([$this->paying('R1', null, '40.00')])], [$this->tick('R1', true, $foreign->id)])->assertSessionHasNoErrors();

        $this->assertSame(0, InvoicePayment::query()->withoutGlobalScopes()->count());
        $this->assertSame('vs_not_found', $this->importResult()['skipped'][0]['state']);
        $this->assertSame(InvoiceStatusEnum::Issued, $foreign->refresh()->status);
    }

    public function test_the_same_file_imported_twice_records_nothing_the_second_time(): void
    {
        $this->invoice('2026015', '100.00');
        $this->invoice('2026016', '50.00');
        $entries = [$this->paying('R1', '2026015', '100.00'), $this->paying('R2', '2026016', '50.00')];

        $this->import([$this->file($entries)], [$this->tick('R1'), $this->tick('R2')])->assertSessionHasNoErrors();
        $this->assertSame(2, InvoicePayment::query()->count());

        $preview = $this->preview([$this->file($entries)])->assertOk();
        $this->assertSame('already_imported', $this->row($preview, 'R1')['state']);
        $this->assertFalse($this->row($preview, 'R1')['include']);
        $this->assertNotNull($this->row($preview, 'R1')['existing_payment_id']);

        $this->import([$this->file($entries)], [$this->tick('R1'), $this->tick('R2')])->assertSessionHasNoErrors();

        $this->assertSame(2, InvoicePayment::query()->count());
        $this->assertSame(0, $this->importResult()['recorded_count']);
        $this->assertSame(2, $this->importResult()['already_imported']);
    }

    public function test_the_same_entry_in_two_files_counts_once_and_a_conflicting_one_is_refused(): void
    {
        $this->invoice('2026017', '100.00');

        $preview = $this->preview([
            $this->file([$this->paying('R1', '2026017', '100.00')], name: 'a.xml'),
            $this->file([$this->paying('R1', '2026017', '100.00')], name: 'b.xml'),
        ])->assertOk();

        $this->assertCount(1, $this->jsonList($preview, 'rows'));
        $this->assertSame(1, $preview->json('overlapping_rows'));
        $this->assertSame(0, $this->row($preview, 'R1')['file_index']);

        $this->preview([
            $this->file([$this->paying('R1', '2026017', '100.00')]),
            $this->file([$this->paying('R1', '2026017', '90.00')]),
        ])->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_a_deleted_bank_payment_is_flagged_and_can_be_recorded_again(): void
    {
        $invoice = $this->invoice('2026018', '100.00');
        $entries = [$this->paying('R1', '2026018', '100.00')];

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();
        $this->webAs($this->accountant, $this->alpha)
            ->delete(route('invoices.payments.destroy', [$invoice, InvoicePayment::query()->sole()]))
            ->assertRedirect();
        $this->assertSame(InvoiceStatusEnum::Overdue, $invoice->refresh()->status);

        $row = $this->row($this->preview([$this->file($entries)])->assertOk(), 'R1');
        $this->assertSame('previously_deleted', $row['state']);
        $this->assertFalse($row['include']);
        $this->assertSame('100.00', $row['amount_to_record']);

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->refresh()->status);
    }

    public function test_a_manual_payment_of_the_same_amount_nearby_is_a_possible_duplicate(): void
    {
        $invoice = $this->invoice('2026019', '100.00');
        $payment = $this->manualPayment($invoice, '40.00', '2026-10-03');

        $preview = $this->preview([$this->file([
            $this->paying('R1', '2026019', '40.00', '2026-10-05'),
            $this->paying('R2', '2026019', '40.00', '2026-10-30'),
        ])])->assertOk();

        $near = $this->row($preview, 'R1');
        $this->assertSame('possible_duplicate', $near['state']);
        $this->assertFalse($near['include']);
        $this->assertSame($payment->id, $near['existing_payment_id']);
        $this->assertSame('40.00', $near['amount_to_record']);
        $this->assertSame('partial', $this->row($preview, 'R2')['state']);
    }

    public function test_files_are_checked_one_by_one_and_only_the_active_firm_contributes_rows(): void
    {
        $this->invoice('2026020', '10.00');

        $preview = $this->preview([
            $this->file([$this->paying('R1', '2026020', '10.00')], name: 'alpha.xml'),
            $this->file([$this->paying('R2', '2026020', '10.00')], self::OTHER_IBAN, 'beta.xml'),
            $this->file([$this->paying('R3', '2026020', '10.00')], self::UNKNOWN_IBAN, 'unknown.xml'),
        ])->assertOk();

        $this->assertCount(1, $this->jsonList($preview, 'rows'));
        $this->assertSame('R1', $preview->json('rows.0.reference'));
        $this->assertSame('matched', $preview->json('files.0.account.status'));
        $this->assertSame('other_company', $preview->json('files.1.account.status'));
        $this->assertSame($this->beta->id, $preview->json('files.1.account.owner_tenant_id'));
        $this->assertSame('Beta', $preview->json('files.1.account.owner_tenant_name'));
        $this->assertSame('unknown_account', $preview->json('files.2.account.status'));
        $this->assertSame('unknown.xml', $preview->json('files.2.name'));
    }

    public function test_only_foreign_files_give_an_empty_preview_and_a_refused_import(): void
    {
        $this->invoice('2026021', '10.00');
        $foreign = [$this->paying('R1', '2026021', '10.00')];

        $this->preview([$this->file($foreign, self::OTHER_IBAN)])->assertOk()->assertJsonPath('rows', []);

        $this->import([$this->file($foreign, self::OTHER_IBAN)], [$this->tick('R1')])->assertSessionHasErrors('files');
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_a_firm_without_iban_reports_it_per_file(): void
    {
        $this->alpha->update(['iban' => null]);

        $this->preview([$this->file([$this->paying('R1', '2026022', '10.00')])])
            ->assertOk()
            ->assertJsonPath('files.0.account.status', 'company_iban_missing')
            ->assertJsonPath('rows', []);
    }

    public function test_a_reference_outside_the_files_is_refused_and_nothing_is_recorded(): void
    {
        $this->invoice('2026023', '10.00');

        $this->import([$this->file([$this->paying('R1', '2026023', '10.00')])], [$this->tick('R1'), $this->tick('XX')])->assertSessionHasErrors('rows');

        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_rows_missing_from_the_decisions_are_excluded(): void
    {
        $this->invoice('2026024', '10.00');
        $this->invoice('2026025', '10.00');

        $this->import([$this->file([$this->paying('R1', '2026024', '10.00'), $this->paying('R2', '2026025', '10.00')])], [$this->tick('R1')])->assertSessionHasNoErrors();

        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(1, $this->importResult()['excluded']);
    }

    public function test_a_state_changed_after_the_preview_skips_only_that_row(): void
    {
        $paidMeanwhile = $this->invoice('2026026', '10.00');
        $this->invoice('2026027', '20.00');
        $entries = [$this->paying('R1', '2026026', '10.00'), $this->paying('R2', '2026027', '20.00')];

        $this->preview([$this->file($entries)])->assertOk();
        $this->manualPayment($paidMeanwhile, '10.00', '2026-10-04');
        $paidMeanwhile->forceFill(['status' => InvoiceStatusEnum::Paid, 'paid_at' => now()])->saveQuietly();

        $this->import([$this->file($entries)], [$this->tick('R1'), $this->tick('R2')])->assertSessionHasNoErrors();

        $result = $this->importResult();
        $this->assertSame(1, $result['recorded_count']);
        $this->assertSame('invoice_paid', $result['skipped'][0]['state']);
        $this->assertSame('R1', $result['skipped'][0]['reference']);
    }

    public function test_debits_are_counted_but_never_recorded(): void
    {
        $this->invoice('2026028', '10.00');
        $entries = [$this->paying('R1', '2026028', '10.00'), $this->debit('D1', '5.00'), $this->debit('D2', '6.00')];

        $preview = $this->preview([$this->file($entries)])->assertOk();

        $this->assertSame(2, $preview->json('ignored_debit_rows'));
        $this->assertCount(1, $this->jsonList($preview, 'rows'));

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_unreadable_credits_are_counted_and_do_not_fail_the_file(): void
    {
        $preview = $this->preview([$this->file([
            $this->paying('R1', '2026029', '10.00'),
            $this->credit('R2', '10.00', '2026-10-05', ['currency' => 'CZK']),
            $this->credit('R3', '10.00', '2026-10-05', ['reference' => null]),
        ])])->assertOk();

        $this->assertCount(1, $this->jsonList($preview, 'rows'));
        $this->assertSame(2, $preview->json('unreadable_credit_rows'));
    }

    public function test_import_sends_nothing_and_writes_one_audit_batch(): void
    {
        $this->invoice('2026030', '10.00');
        $this->invoice('2026031', '10.00');
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Event::fake([InvoiceMarkedOverdue::class]);

        $this->import([$this->file([$this->paying('R1', '2026030', '10.00'), $this->paying('R2', '2026031', '10.00')])], [$this->tick('R1'), $this->tick('R2')])
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
        Event::assertNotDispatched(InvoiceMarkedOverdue::class);

        $batches = Activity::query()->where('subject_type', (new InvoicePayment)->getMorphClass())->pluck('batch_uuid');
        $this->assertCount(2, $batches);
        $this->assertCount(1, $batches->unique());
        $this->assertNotNull($batches->first());
    }

    public function test_a_user_without_edit_invoices_gets_403_everywhere(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $file = $this->file([$this->paying('R1', '2026032', '10.00')]);
        $web = fn () => $this->webAs($cleaner, $this->alpha);

        $web()->get('/invoices/payment-import')->assertForbidden();
        $web()->get('/invoices/payment-import/invoices')->assertForbidden();
        $web()->post('/invoices/payment-import/preview', ['files' => [$file]], self::JSON)->assertForbidden();
        $web()->post('/invoices/payment-import', ['files' => [$file], 'rows' => [$this->tick('R1')]])->assertForbidden();
    }

    public function test_the_page_renders_limits_and_the_flashed_result(): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->get('/invoices/payment-import')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Invoices/PaymentImport')
                ->where('limits.max_files', 12)
                ->where('limits.max_rows', 500)
                ->where('result', null));
    }

    public function test_upload_limits_are_enforced_with_translated_messages(): void
    {
        config(['invoicing.payment_import.max_files' => 2, 'invoicing.payment_import.max_file_kb' => 1]);
        $small = $this->file([$this->paying('R1', null, '1.00')]);

        $this->preview([$small, $small, $small])->assertUnprocessable()->assertJsonPath('errors.files.0', __('app.payment_import_too_many_files', ['max' => 2]));

        $big = UploadedFile::fake()->createWithContent('big.xml', str_repeat('x', 3000));
        $this->preview([$big])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $text = UploadedFile::fake()->createWithContent('vypis.txt', 'x');
        $errors = $this->errors($this->preview([$text])->assertUnprocessable());
        $this->assertSame(__('app.expense_import_file_invalid'), $errors['files.0'][0]);
    }

    public function test_an_unreadable_file_refuses_the_whole_upload_naming_the_file(): void
    {
        $response = $this->preview([
            $this->file([$this->paying('R1', null, '1.00')]),
            UploadedFile::fake()->createWithContent('rozbity.xml', '<nope'),
        ])->assertUnprocessable();

        $this->assertStringContainsString('rozbity.xml', $this->errors($response)['files.1'][0]);
    }

    public function test_too_many_credits_are_refused(): void
    {
        config(['invoicing.payment_import.max_rows' => 2]);

        $this->preview([$this->file([
            $this->paying('R1', null, '1.00'),
            $this->paying('R2', null, '1.00'),
            $this->paying('R3', null, '1.00'),
        ])])->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_invoice_search_finds_open_invoices_and_ranks_the_exact_balance_first(): void
    {
        $this->invoice('2026040', '100.00', 'issued', ['customer_name' => 'Zakaznik Alfa', 'due_date' => '2026-10-10']);
        $exact = $this->invoice('2026041', '75.50', 'overdue', ['customer_name' => 'Zakaznik Beta', 'due_date' => '2026-10-30']);
        $this->invoice('2026042', '10.00', 'paid', ['customer_name' => 'Zakaznik Paid']);
        $this->invoice('2026043', '10.00', 'cancelled', ['customer_name' => 'Zakaznik Cancelled']);
        $this->invoice('2026044', '10.00', 'draft', ['customer_name' => 'Zakaznik Draft', 'number' => null]);
        $this->invoice('2026045', '10.00', 'issued', ['customer_name' => 'Zakaznik Foreign'], $this->beta);

        $web = fn () => $this->webAs($this->accountant, $this->alpha);

        $all = $web()->getJson('/invoices/payment-import/invoices')->assertOk();
        $this->assertSame(['FA-2026040', 'FA-2026041'], collect($this->jsonList($all))->pluck('number')->all());

        $ranked = $web()->getJson('/invoices/payment-import/invoices?amount=75.50')->assertOk();
        $this->assertSame($exact->id, $ranked->json('0.id'));
        $this->assertSame('75.50', $ranked->json('0.balance_due'));

        $byName = $web()->getJson('/invoices/payment-import/invoices?search=beta')->assertOk();
        $this->assertSame([$exact->id], collect($this->jsonList($byName))->pluck('id')->all());

        $byNumber = $web()->getJson('/invoices/payment-import/invoices?search=FA-2026040')->assertOk();
        $this->assertCount(1, $this->jsonList($byNumber));

        $this->assertCount(0, $this->jsonList($web()->getJson('/invoices/payment-import/invoices?search=Draft')));
        $this->assertCount(0, $this->jsonList($web()->getJson('/invoices/payment-import/invoices?search=Foreign')));
        $web()->getJson('/invoices/payment-import/invoices?amount=0')->assertUnprocessable();
    }

    public function test_the_database_rejects_a_second_live_payment_with_the_same_key(): void
    {
        $invoice = $this->invoice('2026050', '100.00');
        $this->manualPayment($invoice, '10.00', '2026-10-01', $this->key('R1'));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->manualPayment($invoice, '10.00', '2026-10-02', $this->key('R1'));
    }

    public function test_a_non_eur_invoice_is_not_payable_by_vs_or_by_hand_and_is_not_searchable(): void
    {
        $czk = $this->invoice('2026060', '100.00', 'issued', ['currency' => CurrencyEnum::CZK]);
        $entries = [$this->paying('R1', '2026060', '100.00'), $this->paying('R2', null, '100.00')];

        $preview = $this->preview([$this->file($entries)])->assertOk();
        $this->assertSame('invoice_not_payable', $this->row($preview, 'R1')['state']);
        $this->assertFalse($this->row($preview, 'R1')['include']);

        $this->import([$this->file($entries)], [$this->tick('R1'), $this->tick('R2', true, $czk->id)])->assertSessionHasNoErrors();

        $this->assertSame(0, InvoicePayment::query()->count());
        $this->assertSame(['invoice_not_payable', 'invoice_not_payable'], array_column($this->importResult()['skipped'], 'state'));

        $found = $this->jsonList($this->webAs($this->accountant, $this->alpha)->getJson('/invoices/payment-import/invoices?search=2026060'));
        $this->assertSame([], $found);
    }

    public function test_a_long_bank_text_does_not_push_the_excess_out_of_the_note(): void
    {
        $this->invoice('2026061', '100.00');
        $entries = [$this->credit('R1', '130.00', '2026-10-05', [
            'counterparty' => null,
            'payer' => str_repeat('P', 400),
            'note' => str_repeat('T', 900),
            'end_to_end' => '/VS2026061/SS',
        ])];

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $note = (string) InvoicePayment::query()->sole()->note;
        $this->assertLessThanOrEqual(500, mb_strlen($note));
        $this->assertStringContainsString('30.00', $note);
        $this->assertStringContainsString(__('app.payment_import_note_excess', ['amount' => '30.00']), $note);
    }

    public function test_a_unique_violation_while_writing_is_retried(): void
    {
        $invoice = $this->invoice('2026062', '100.00');
        $entries = [$this->paying('R1', '2026062', '100.00')];
        $calls = 0;

        InvoicePayment::creating(function (InvoicePayment $payment) use (&$calls, $invoice): void {
            if ($calls++ > 0) {
                return;
            }

            DB::table('invoice_payments')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $invoice->tenant_id,
                'invoice_id' => $invoice->id,
                'amount' => '1.00',
                'paid_on' => '2026-10-01',
                'payment_type' => PaymentTypeEnum::Transfer->value,
                'source' => PaymentSourceEnum::Manual->value,
                'external_reference' => $payment->external_reference,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();

        $this->assertSame(2, $calls);
        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(1, $this->importResult()['recorded_count']);
    }

    public function test_the_same_entry_key_is_allowed_once_per_firm_sharing_an_iban(): void
    {
        $this->beta->update(['iban' => self::STATEMENT_IBAN]);
        $alphaInvoice = $this->invoice('2026063', '10.00');
        $betaInvoice = $this->invoice('2026063', '10.00', 'issued', [], $this->beta);
        $entries = [$this->paying('R1', '2026063', '10.00')];

        $this->import([$this->file($entries)], [$this->tick('R1')])->assertSessionHasNoErrors();
        $this->webAs($this->accountant, $this->beta)
            ->post('/invoices/payment-import', ['files' => [$this->file($entries)], 'rows' => [$this->tick('R1')]])
            ->assertSessionHasNoErrors();

        $payments = InvoicePayment::query()->withoutGlobalScopes()->get();
        $this->assertCount(2, $payments);
        $this->assertSame([$this->key('R1')], $payments->pluck('external_reference')->unique()->values()->all());
        $this->assertSame(InvoiceStatusEnum::Paid, $alphaInvoice->refresh()->status);
        $this->assertSame(InvoiceStatusEnum::Paid, $betaInvoice->refresh()->status);
    }

    public function test_the_invoice_search_is_throttled_separately_and_loosely(): void
    {
        $web = fn () => $this->webAs($this->accountant, $this->alpha);

        for ($i = 0; $i < 30; $i++) {
            $web()->getJson('/invoices/payment-import/invoices')->assertOk();
        }
    }
}
