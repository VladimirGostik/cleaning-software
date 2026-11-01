<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Data\Expenses\ExpenseDraftData;
use App\Data\Expenses\ExpenseProposalData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

final class ExpenseAnalyzeTest extends TestCase
{
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    private const array JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExpenses();
    }

    private function pdf(string $name = 'receipt.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nreceipt");
    }

    private function disguisedExecutable(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF");

        return new UploadedFile($path, 'malware.pdf', 'application/pdf', null, true);
    }

    public function test_receipt_analysis_returns_a_prefill_naming_firm_b_and_saves_nothing(): void
    {
        $this->analyzer->draft = new ExpenseDraftData(
            '2026-10-02', '12,40', CurrencyEnum::EUR, null, 'Metro', 'Detergents',
            new ExpenseProposalData(ExpenseCategoryEnum::CleaningSupplies, $this->beta->id, 0.87, 'Supplier invoice'),
        );

        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses/analyze', ['receipt' => $this->pdf()], self::JSON)
            ->assertOk()
            ->assertJsonPath('amount', '12.40')
            ->assertJsonPath('date', '2026-10-02')
            ->assertJsonPath('supplier_name', 'Metro')
            ->assertJsonPath('proposal.category', 'cleaning_supplies')
            ->assertJsonPath('proposal.tenant_id', $this->beta->id)
            ->assertJsonPath('proposal.confidence', 0.87);

        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame(['application/pdf'], $this->analyzer->receiptMimes);
    }

    public function test_provider_context_lists_only_manageable_companies_with_ico_and_iban(): void
    {
        $this->alpha->forceFill(['ico' => '12345678', 'iban' => 'SK8975000000000123456789'])->save();
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Sekretárka', $this->accountant);

        $this->webAs($this->accountant, $this->alpha)->post('/expenses/analyze', ['receipt' => $this->pdf()], self::JSON)->assertOk();

        $companies = $this->analyzer->contexts[0]->companies;
        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], array_map(fn ($company) => $company->id, $companies));
        $alpha = collect($companies)->firstWhere('id', $this->alpha->id);
        $this->assertNotNull($alpha);
        $this->assertSame('12345678', $alpha->ico);
        $this->assertSame('SK8975000000000123456789', $alpha->iban);
    }

    public function test_ai_output_is_untrusted_and_sanitised(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Sekretárka', $this->accountant);
        $this->analyzer->draft = new ExpenseDraftData(
            '31.12.2026', '-5', CurrencyEnum::EUR, 'abc', '=HYPERLINK("http://evil")', str_repeat('x', 900),
            new ExpenseProposalData(ExpenseCategoryEnum::Fuel, $gamma->id, 4.2, null),
        );

        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses/analyze', ['receipt' => $this->pdf()], self::JSON)
            ->assertOk()
            ->assertJsonPath('date', null)
            ->assertJsonPath('amount', null)
            ->assertJsonPath('vat_amount', null)
            ->assertJsonPath('supplier_name', 'HYPERLINK("http://evil")')
            ->assertJsonPath('proposal.tenant_id', null)
            ->assertJsonPath('proposal.confidence', 1);

        $description = $this->webAs($this->accountant, $this->alpha)->post('/expenses/analyze', ['receipt' => $this->pdf()], self::JSON)->json('description');
        $this->assertIsString($description);
        $this->assertSame(500, mb_strlen($description));
    }

    public function test_text_analysis_goes_through_the_text_method(): void
    {
        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses/analyze', ['text' => 'Tankovanie 45,50 EUR Shell'])
            ->assertOk()
            ->assertJsonPath('proposal.category', 'fuel');

        $this->assertSame(['Tankovanie 45,50 EUR Shell'], $this->analyzer->texts);
        $this->assertSame([], $this->analyzer->receiptMimes);
    }

    public function test_unavailable_provider_yields_422_and_creates_no_row(): void
    {
        $this->analyzer->unavailable = true;

        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses/analyze', ['receipt' => $this->pdf()], self::JSON)
            ->assertUnprocessable()
            ->assertJsonPath('errors.ai.0', __('app.ai_unavailable'));

        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses/analyze', ['text' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ai.0', __('app.ai_unavailable'));

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_input_is_validated(): void
    {
        $web = fn () => $this->webAs($this->accountant, $this->alpha);

        $web()->post('/expenses/analyze', [], self::JSON)->assertUnprocessable();
        $web()->post('/expenses/analyze', ['receipt' => $this->pdf(), 'text' => 'both'], self::JSON)->assertUnprocessable();
        $web()->post('/expenses/analyze', ['receipt' => $this->disguisedExecutable()], self::JSON)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
        $web()->post('/expenses/analyze', ['receipt' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf')], self::JSON)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');
        $web()->post('/expenses/analyze', ['text' => str_repeat('a', 5001)], self::JSON)->assertUnprocessable();

        $this->assertSame([], $this->analyzer->contexts);
    }

    public function test_only_users_who_manage_expenses_can_analyze(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');

        $this->webAs($secretary, $this->alpha)->post('/expenses/analyze', ['text' => 'x'], self::JSON)->assertForbidden();
        $this->apiAs($secretary, $this->alpha)->postJson('/api/expenses/analyze', ['text' => 'x'])->assertForbidden();
    }

    public function test_saving_with_a_firm_where_the_actor_cannot_manage_expenses_is_forbidden(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Sekretárka', $this->accountant);

        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses', $this->expensePayload(['tenant_id' => $gamma->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_confirmed_proposal_is_stored_as_an_annotation(): void
    {
        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses', $this->expensePayload([
                'tenant_id' => $this->beta->id,
                'source' => 'receipt',
                'ai_proposal' => ['category' => 'fuel', 'tenant_id' => $this->beta->id, 'confidence' => 0.9, 'reason' => 'Fuel station'],
            ]))
            ->assertCreated()
            ->assertJsonPath('ai_proposal.category', 'fuel')
            ->assertJsonPath('source', 'receipt');

        $stored = Expense::query()->withoutGlobalScopes()->sole()->ai_proposal;
        $this->assertSame('claude-sonnet-5-5', $stored['model'] ?? null);
    }

    public function test_analysis_is_throttled_per_user(): void
    {
        config(['ai.throttle.analyze' => 2]);
        RateLimiter::clear('ai-analyze');

        $api = fn () => $this->apiAs($this->accountant, $this->alpha)->postJson('/api/expenses/analyze', ['text' => 'x']);

        $api()->assertOk();
        $api()->assertOk();
        $api()->assertStatus(429);
    }
}
