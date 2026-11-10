<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Ai\Guard\PromptGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\CreatesCamtStatements;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

final class TokenUploadTest extends TestCase
{
    use CreatesCamtStatements;
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    private const array JSON = ['Accept' => 'application/json'];

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setUpExpenses();
        $this->alpha->update(['iban' => self::STATEMENT_IBAN]);
        $this->beta->update(['iban' => self::OTHER_IBAN]);
        config()->set('token_uploads.enabled', true);
        $this->token = $this->accountant->createToken('claude', ['mcp'])->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function send(string $path, array $data, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->post('/api/token/'.$path, $data, self::JSON);
    }

    /** @return array<string, mixed> */
    private function paying(string $reference, ?string $vs, string $amount, string $payer = 'Platca A', string $note = 'Faktura za upratovanie'): array
    {
        return $this->credit($reference, $amount, '2026-10-05', [
            'counterparty' => null,
            'payer' => $payer,
            'payer_iban' => 'SK3333000000003333333333',
            'note' => $note,
            'end_to_end' => $vs === null ? 'NOTPROVIDED' : '/VS'.str_pad($vs, 10, '0', STR_PAD_LEFT).'/SS0000000000',
        ]);
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function statement(array $entries, string $iban = self::STATEMENT_IBAN): UploadedFile
    {
        return $this->camtFile($this->camtXml($entries, $iban, period: ['2026-10-01T00:00:00.000+02:00', '2026-10-31T23:59:59.000+01:00']));
    }

    private function invoice(string $vs, string $total = '100.00'): Invoice
    {
        return Invoice::factory()->issued()->create([
            'tenant_id' => $this->alpha->id,
            'variable_symbol' => $vs,
            'number' => 'FA-'.$vs,
            'total' => $total,
            'due_date' => '2026-10-20',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function mixedEntries(): array
    {
        $this->invoice('1001');
        $this->invoice('1002');
        $this->invoice('1003');

        return [
            $this->paying('R-MATCH', '1001', '100.00'),
            $this->paying('R-PARTIAL', '1002', '40.00'),
            $this->paying('R-OVER', '1003', '150.00'),
            $this->paying('R-NOVS', null, '20.00'),
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('payslips.pdf', "%PDF-1.4\npayslips");
    }

    public function test_every_route_answers_404_while_disabled(): void
    {
        config()->set('token_uploads.enabled', false);
        $file = $this->statement([$this->paying('R1', '1001', '10.00')]);
        $base = ['company_id' => $this->alpha->id];

        $this->send('invoice-payments/preview', [...$base, 'files' => [$file]])->assertNotFound();
        $this->send('invoice-payments', [...$base, 'files' => [$file]])->assertNotFound();
        $this->send('expenses/receipt', [...$base, 'expense_ids' => [fake()->uuid()], 'file' => $this->pdf()])->assertNotFound();
    }

    public function test_a_mobile_token_is_refused(): void
    {
        $mobile = $this->accountant->createToken('phone', ['mobile'])->plainTextToken;

        $this->send('invoice-payments/preview', ['company_id' => $this->alpha->id, 'files' => [$this->statement([])]], $mobile)->assertForbidden();
    }

    public function test_a_user_without_edit_invoices_in_the_firm_is_refused(): void
    {
        $cleaner = $this->member($this->alpha, 'Zákazník');
        $token = $cleaner->createToken('claude', ['mcp'])->plainTextToken;
        $data = ['company_id' => $this->alpha->id, 'files' => [$this->statement([$this->paying('R1', '1001', '10.00')])]];

        $this->send('invoice-payments/preview', $data, $token)->assertForbidden();
        $this->send('invoice-payments', $data, $token)->assertForbidden();
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_a_firm_the_user_does_not_belong_to_is_refused(): void
    {
        $outsider = Tenant::factory()->create(['iban' => self::STATEMENT_IBAN]);

        $this->send('invoice-payments/preview', ['company_id' => $outsider->id, 'files' => [$this->statement([])]])->assertForbidden();
    }

    public function test_preview_returns_the_web_row_states_without_payer_name_or_note(): void
    {
        $response = $this->send('invoice-payments/preview', ['company_id' => $this->alpha->id, 'files' => [$this->statement($this->mixedEntries())]])->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->json('rows');
        $states = array_column($rows, 'state', 'reference');
        ksort($states);

        $this->assertSame(['R-MATCH' => 'matched', 'R-NOVS' => 'no_vs', 'R-OVER' => 'overpaid', 'R-PARTIAL' => 'partial'], $states);

        foreach ($rows as $row) {
            $this->assertNull($row['payer_name']);
            $this->assertNull($row['note']);
        }

        $this->assertStringNotContainsString('Platca A', (string) $response->getContent());
        $this->assertStringNotContainsString('Faktura za upratovanie', (string) $response->getContent());
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_import_records_only_the_ticked_rows_minus_the_excluded_ones_and_a_repeat_records_nothing(): void
    {
        $entries = $this->mixedEntries();
        $data = ['company_id' => $this->alpha->id, 'files' => [$this->statement($entries)], 'exclude_references' => ['R-PARTIAL']];

        $first = $this->send('invoice-payments', $data)->assertOk();

        $this->assertSame(1, $first->json('recorded_count'));
        $this->assertSame('100.00', $first->json('recorded_total'));
        $this->assertSame(1, InvoicePayment::query()->count());
        $this->assertSame(1, InvoicePayment::query()->where('invoice_id', Invoice::query()->where('variable_symbol', '1001')->value('id'))->count());

        $again = $this->send('invoice-payments', ['company_id' => $this->alpha->id, 'files' => [$this->statement($entries)], 'exclude_references' => ['R-PARTIAL']])->assertOk();

        $this->assertSame(0, $again->json('recorded_count'));
        $this->assertSame(1, $again->json('already_imported'));
        $this->assertSame(1, InvoicePayment::query()->count());
    }

    public function test_import_records_partial_rows_and_never_an_overpaid_or_unmatched_row(): void
    {
        $response = $this->send('invoice-payments', ['company_id' => $this->alpha->id, 'files' => [$this->statement($this->mixedEntries())]])->assertOk();

        $this->assertSame(2, $response->json('recorded_count'));
        $this->assertSame('140.00', $response->json('recorded_total'));
        $this->assertSame(2, $response->json('excluded'));
        $this->assertSame([], $response->json('overpayments'));
        $this->assertSame(2, InvoicePayment::query()->count());
    }

    public function test_a_statement_of_another_account_gives_422_on_the_file(): void
    {
        $file = $this->statement([$this->paying('R1', '1001', '10.00')], self::OTHER_IBAN);
        $data = ['company_id' => $this->alpha->id, 'files' => [$file]];

        $this->send('invoice-payments/preview', $data)->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->send('invoice-payments', $data)->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->assertSame(0, InvoicePayment::query()->count());
    }

    public function test_a_non_xml_file_and_a_missing_company_are_validation_errors(): void
    {
        $this->send('invoice-payments', ['files' => [UploadedFile::fake()->create('x.pdf', 1)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id', 'files.0']);
    }

    public function test_one_receipt_is_attached_to_two_expenses(): void
    {
        $a = Expense::factory()->forTenant($this->alpha)->create();
        $b = Expense::factory()->forTenant($this->alpha)->create();

        $response = $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$a->id, $b->id], 'file' => $this->pdf()])->assertOk();

        $this->assertSame([$a->id, $b->id], $response->json('expense_ids'));
        $this->assertSame('payslips.pdf', $response->json('file_name'));

        foreach ([$a, $b] as $expense) {
            $media = $expense->fresh()?->getFirstMedia(Expense::RECEIPT_COLLECTION);
            $this->assertNotNull($media);
            $this->assertSame('local', $media->disk);
            Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
        }

        $this->assertSame(2, Expense::query()->has('media')->count());
    }

    public function test_an_expense_that_already_has_a_receipt_is_refused_and_nothing_is_attached(): void
    {
        $fresh = Expense::factory()->forTenant($this->alpha)->create();
        $taken = Expense::factory()->forTenant($this->alpha)->create();
        app()->instance('current_tenant_id', $this->alpha->id);
        $taken->addMedia(UploadedFile::fake()->createWithContent('old.pdf', "%PDF-1.4\nold"))->toMediaCollection(Expense::RECEIPT_COLLECTION);
        app()->forgetInstance('current_tenant_id');

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$fresh->id, $taken->id], 'file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expense_ids.1');

        $this->assertNull($fresh->fresh()?->getFirstMedia(Expense::RECEIPT_COLLECTION));
        $this->assertSame('old.pdf', $taken->fresh()?->getFirstMedia(Expense::RECEIPT_COLLECTION)?->file_name);
    }

    public function test_an_undocumented_cash_expense_refuses_a_receipt_and_nothing_is_attached(): void
    {
        $plain = Expense::factory()->forTenant($this->alpha)->create();
        $employee = TenantMembership::factory()->create(['tenant_id' => $this->alpha->id, 'user_id' => null]);
        $cash = Expense::factory()->undocumented($employee)->create();

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$plain->id, $cash->id], 'file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expense_ids.1');

        $this->assertSame(0, Expense::query()->withoutGlobalScopes()->has('media')->count());
    }

    public function test_the_404_for_an_unknown_expense_does_not_name_the_model(): void
    {
        $own = Expense::factory()->forTenant($this->alpha)->create();

        $response = $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$own->id, fake()->uuid()], 'file' => $this->pdf()])->assertNotFound();

        $this->assertStringNotContainsString('Expense', (string) $response->getContent());
    }

    public function test_a_foreign_or_unknown_expense_gives_404_and_nothing_is_attached(): void
    {
        $own = Expense::factory()->forTenant($this->alpha)->create();
        $foreign = Expense::factory()->forTenant($this->beta)->create();

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$own->id, $foreign->id], 'file' => $this->pdf()])->assertNotFound();
        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$own->id, fake()->uuid()], 'file' => $this->pdf()])->assertNotFound();

        $this->assertSame(0, Expense::query()->withoutGlobalScopes()->has('media')->count());
    }

    public function test_an_employee_expense_needs_view_employee_personal_data(): void
    {
        $clerk = $this->member($this->alpha, 'Sekretárka');
        $this->grantPermissions($clerk, $this->alpha, [PermissionEnum::ViewExpenses, PermissionEnum::ManageExpenses]);
        $employee = TenantMembership::factory()->create(['tenant_id' => $this->alpha->id, 'user_id' => null]);
        $plain = Expense::factory()->forTenant($this->alpha)->create();
        $payslip = Expense::factory()->forEmployee($employee, '2026-10')->create();
        $token = $clerk->createToken('claude', ['mcp'])->plainTextToken;

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$plain->id, $payslip->id], 'file' => $this->pdf()], $token)->assertForbidden();
        $this->assertSame(0, Expense::query()->withoutGlobalScopes()->has('media')->count());

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$plain->id], 'file' => $this->pdf()], $token)->assertOk();
    }

    public function test_a_wrong_file_type_is_refused(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->create();

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$expense->id], 'file' => UploadedFile::fake()->createWithContent('run.sh', "#!/bin/sh\necho x")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_the_same_expense_id_twice_is_a_validation_error(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->create();

        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$expense->id, $expense->id], 'file' => $this->pdf()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expense_ids.0');
    }

    public function test_the_prompt_guard_lock_blocks_writes_but_not_preview(): void
    {
        app(PromptGuard::class)->restrictMcp($this->accountant);
        $expense = Expense::factory()->forTenant($this->alpha)->create();
        $file = $this->statement($this->mixedEntries());

        $this->send('invoice-payments/preview', ['company_id' => $this->alpha->id, 'files' => [$file]])->assertOk();
        $this->send('invoice-payments', ['company_id' => $this->alpha->id, 'files' => [$file]])->assertStatus(423);
        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$expense->id], 'file' => $this->pdf()])->assertStatus(423);

        $this->assertSame(0, InvoicePayment::query()->count());
        $this->assertNull($expense->fresh()?->getFirstMedia(Expense::RECEIPT_COLLECTION));
    }

    public function test_the_eleventh_call_within_a_minute_is_throttled(): void
    {
        RateLimiter::clear('token:'.$this->accountant->tokens()->firstOrFail()->id);
        $data = ['company_id' => $this->alpha->id, 'files' => [$this->statement([])]];

        for ($i = 0; $i < 10; $i++) {
            $this->send('invoice-payments/preview', $data)->assertOk();
        }

        $this->send('invoice-payments/preview', $data)->assertStatus(429);
    }

    public function test_each_call_writes_one_audit_row_without_file_or_bank_text(): void
    {
        $this->invoice('1001');
        $this->invoice('1002');
        $expense = Expense::factory()->forTenant($this->alpha)->create();
        $files = fn (): array => [$this->statement($this->twoEntries())];

        $this->send('invoice-payments/preview', ['company_id' => $this->alpha->id, 'files' => $files()])->assertOk();
        $this->send('invoice-payments', ['company_id' => $this->alpha->id, 'files' => $files()])->assertOk();
        $this->send('expenses/receipt', ['company_id' => $this->alpha->id, 'expense_ids' => [$expense->id], 'file' => $this->pdf()])->assertOk();

        $rows = Activity::query()->withoutGlobalScopes()->where('log_name', 'ai')->orderBy('created_at')->get();

        $this->assertSame(
            ['tool.upload.invoice_payments.preview', 'tool.upload.invoice_payments.import', 'tool.upload.expense_receipt'],
            $rows->pluck('description')->all(),
        );

        $import = $rows->skip(1)->firstOrFail();
        $this->assertSame(2, data_get($import->properties, 'recorded'));
        $this->assertSame(1, data_get($import->properties, 'arguments.files_count'));
        $this->assertSame($this->alpha->id, data_get($import->properties, 'company_id'));

        $dump = (string) json_encode($rows->pluck('properties')->all());
        $this->assertStringNotContainsString('Platca A', $dump);
        $this->assertStringNotContainsString('Faktura za upratovanie', $dump);
        $this->assertStringNotContainsString('payslips', $dump);
    }

    /** @return list<array<string, mixed>> */
    private function twoEntries(): array
    {
        return [
            $this->paying('R-MATCH', '1001', '100.00'),
            $this->paying('R-PARTIAL', '1002', '40.00'),
        ];
    }

    public function test_a_revoked_token_gets_401(): void
    {
        $this->accountant->tokens()->delete();

        $this->send('invoice-payments/preview', ['company_id' => $this->alpha->id, 'files' => [$this->statement([])]])->assertUnauthorized();
    }

    public function test_there_is_no_route_to_delete_or_change_payments(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->token)->deleteJson('/api/token/invoice-payments')->assertStatus(405);
        $this->withToken($this->token)->putJson('/api/token/expenses/receipt')->assertStatus(405);
    }
}
