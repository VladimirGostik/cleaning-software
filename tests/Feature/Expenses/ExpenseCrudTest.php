<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

final class ExpenseCrudTest extends TestCase
{
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExpenses();
    }

    public function test_accountant_creates_a_confirmed_expense_and_it_is_audited_under_the_firm(): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses', $this->expensePayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $expense = Expense::query()->withoutGlobalScopes()->sole();

        $this->assertSame($this->alpha->id, $expense->tenant_id);
        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
        $this->assertSame(ExpenseCategoryEnum::Fuel, $expense->category);
        $this->assertSame('45.50', $expense->amount);
        $this->assertNotNull($expense->confirmed_at);
        $this->assertSame($this->accountant->id, $expense->created_by_user_id);
        $this->assertTrue(Activity::query()->where('subject_id', $expense->id)->where('tenant_id', $this->alpha->id)->exists());
    }

    public function test_expense_without_category_is_a_draft(): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses', $this->expensePayload(['category' => null]))
            ->assertSessionHasNoErrors();

        $expense = Expense::query()->withoutGlobalScopes()->sole();

        $this->assertSame(ExpenseStatusEnum::Draft, $expense->status);
        $this->assertNull($expense->confirmed_at);
    }

    public function test_secretary_cannot_view_or_manage_expenses(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($secretary, $this->alpha)->get('/expenses')->assertForbidden();
        $this->webAs($secretary, $this->alpha)->get("/expenses/{$expense->id}")->assertForbidden();
        $this->webAs($secretary, $this->alpha)->post('/expenses', $this->expensePayload())->assertForbidden();
        $this->webAs($secretary, $this->alpha)->delete("/expenses/{$expense->id}")->assertForbidden();
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_view_only_user_cannot_change_expenses(): void
    {
        $viewer = $this->member($this->alpha, 'Interná upratovačka');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $viewer->givePermissionTo(PermissionEnum::ViewExpenses->value);
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($viewer, $this->alpha)->get('/expenses')->assertOk();
        $this->webAs($viewer, $this->alpha)->put("/expenses/{$expense->id}", $this->expensePayload())->assertForbidden();
    }

    public function test_confirm_requires_a_category_and_only_works_on_drafts(): void
    {
        $draft = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->draft()->create();

        $this->webAs($this->accountant, $this->alpha)
            ->postJson("/expenses/{$draft->id}/confirm", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->webAs($this->accountant, $this->alpha)
            ->post("/expenses/{$draft->id}/confirm", ['category' => 'rent'])
            ->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame(ExpenseStatusEnum::Confirmed, $draft->status);
        $this->assertSame(ExpenseCategoryEnum::Rent, $draft->category);
        $this->assertNotNull($draft->confirmed_at);

        $this->webAs($this->accountant, $this->alpha)
            ->postJson("/expenses/{$draft->id}/confirm", ['category' => 'fuel'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_confirmed_status_without_category_is_rejected(): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->postJson('/expenses', $this->expensePayload(['category' => null, 'status' => 'confirmed']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)
            ->putJson("/expenses/{$expense->id}", $this->expensePayload(['category' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->refresh()->status);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'zero' => [['amount' => '0'], 'amount'],
            'negative' => [['amount' => '-5.00'], 'amount'],
            'three decimals' => [['amount' => '10.123'], 'amount'],
            'text' => [['amount' => 'abc'], 'amount'],
            'vat above amount' => [['amount' => '10.00', 'vat_amount' => '12.00'], 'vat_amount'],
            'bad date' => [['date' => '2026-13-40'], 'date'],
            'unknown currency' => [['currency' => 'GBP'], 'currency'],
            'unknown category' => [['category' => 'luxury'], 'category'],
            'forged source' => [['source' => 'bank_statement'], 'source'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidAmounts')]
    public function test_invalid_input_is_rejected(array $overrides, string $field): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->postJson('/expenses', $this->expensePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_update_keeps_the_firm_and_the_confirmation_time(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create(['confirmed_at' => now()->subDay()]);
        $confirmedAt = $expense->confirmed_at;

        $this->webAs($this->accountant, $this->alpha)
            ->put("/expenses/{$expense->id}", $this->expensePayload(['amount' => '99.90', 'tenant_id' => $this->beta->id]))
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('99.90', $expense->amount);
        $this->assertSame($this->alpha->id, $expense->tenant_id);
        $this->assertSame($confirmedAt?->toIso8601String(), $expense->confirmed_at?->toIso8601String());
    }

    public function test_delete_soft_deletes(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)->delete("/expenses/{$expense->id}")->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_foreign_firm_expense_is_not_found_through_a_real_request(): void
    {
        $foreign = Expense::factory()->forTenant($this->beta)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)->get("/expenses/{$foreign->id}")->assertNotFound();
        $this->webAs($this->accountant, $this->alpha)->delete("/expenses/{$foreign->id}")->assertNotFound();
        $this->webAs($this->accountant, $this->alpha)->put("/expenses/{$foreign->id}", $this->expensePayload())->assertNotFound();
        $this->webAs($this->accountant, $this->alpha)->post("/expenses/{$foreign->id}/confirm", ['category' => 'fuel'])->assertNotFound();
        $this->assertNotSoftDeleted('expenses', ['id' => $foreign->id]);
    }

    public function test_index_lists_the_active_firm_and_filters_by_status(): void
    {
        $confirmed = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        $draft = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->draft()->create();
        Expense::factory()->forTenant($this->beta)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Index')
                ->has('expenses.data', 2)
                ->has('context.companies', 2)
                ->has('context.categories', 13));

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses?filter[status]=draft')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $draft->id));

        $this->assertNotSame($confirmed->id, $draft->id);
    }

    public function test_show_exposes_detail_and_capabilities(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->draft()->create([
            'ai_proposal' => ['category' => 'fuel', 'tenant_id' => $this->alpha->id, 'confidence' => 0.7, 'reason' => 'x', 'model' => 'm'],
        ]);

        $this->webAs($this->accountant, $this->alpha)
            ->get("/expenses/{$expense->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Expenses/Show')
                ->where('expense.id', $expense->id)
                ->where('expense.status', 'draft')
                ->where('expense.proposed_category', 'fuel')
                ->where('expense.ai_proposal.confidence', 0.7)
                ->where('expense.receipt', null)
                ->where('expense.can.confirm', true));
    }

    public function test_api_scope_all_spans_firms_and_scope_company_does_not(): void
    {
        $inAlpha = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        $inBeta = Expense::factory()->forTenant($this->beta)->createdBy($this->accountant)->create();
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Sekretárka', $this->accountant);
        $hidden = Expense::factory()->forTenant($gamma)->create();

        /** @var list<string> $all */
        $all = $this->apiAs($this->accountant, $this->alpha)->getJson('/api/expenses?scope=all')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$inAlpha->id, $inBeta->id], $all);
        $this->assertNotContains($hidden->id, $all);

        /** @var list<string> $company */
        $company = $this->apiAs($this->accountant, $this->alpha)->getJson('/api/expenses')->assertOk()->json('data.*.id');
        $this->assertSame([$inAlpha->id], $company);
    }

    public function test_api_list_item_shape_and_per_page_clamp(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create(['amount' => '12.30']);

        $this->apiAs($this->accountant, $this->alpha)
            ->getJson('/api/expenses?per_page=1000')
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonPath('data.0.id', $expense->id)
            ->assertJsonPath('data.0.tenant_name', 'Alpha')
            ->assertJsonPath('data.0.amount', '12.30')
            ->assertJsonPath('data.0.has_receipt', false);
    }

    public function test_api_can_create_in_another_firm_the_user_manages(): void
    {
        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses', $this->expensePayload(['tenant_id' => $this->beta->id]))
            ->assertCreated()
            ->assertJsonPath('tenant_id', $this->beta->id)
            ->assertJsonPath('tenant_name', 'Beta');

        $expense = Expense::query()->withoutGlobalScopes()->sole();
        $this->assertSame($this->beta->id, $expense->tenant_id);
        $this->assertTrue(Activity::query()->where('subject_id', $expense->id)->where('tenant_id', $this->beta->id)->exists());
    }

    public function test_api_create_in_a_firm_without_manage_expenses_is_forbidden(): void
    {
        $gamma = $this->firm('Gamma');
        $this->member($gamma, 'Sekretárka', $this->accountant);

        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses', $this->expensePayload(['tenant_id' => $gamma->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_api_create_in_a_foreign_firm_the_user_does_not_belong_to_is_forbidden(): void
    {
        $outsider = $this->firm('Outsider');

        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses', $this->expensePayload(['tenant_id' => $outsider->id]))
            ->assertForbidden();
    }

    public function test_api_receipt_uuid_is_refused_without_a_session(): void
    {
        $this->apiAs($this->accountant, $this->alpha)
            ->postJson('/api/expenses', $this->expensePayload(['receipt_uuid' => fake()->uuid()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt_uuid');
    }

    public function test_api_show_update_confirm_delete_roundtrip(): void
    {
        $draft = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->draft()->create();

        $this->apiAs($this->accountant, $this->alpha)
            ->getJson("/api/expenses/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('can.confirm', true);

        $this->apiAs($this->accountant, $this->alpha)
            ->postJson("/api/expenses/{$draft->id}/confirm", ['category' => 'rent'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('category', 'rent');

        $this->apiAs($this->accountant, $this->alpha)
            ->putJson("/api/expenses/{$draft->id}", $this->expensePayload(['amount' => '9.00']))
            ->assertOk()
            ->assertJsonPath('amount', '9.00');

        $this->apiAs($this->accountant, $this->alpha)->deleteJson("/api/expenses/{$draft->id}")->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $draft->id]);
    }

    public function test_api_foreign_firm_expense_is_not_found_and_form_context_lists_manageable_firms(): void
    {
        $foreign = Expense::factory()->forTenant($this->beta)->createdBy($this->accountant)->create();

        $this->apiAs($this->accountant, $this->alpha)->getJson("/api/expenses/{$foreign->id}")->assertNotFound();

        $this->apiAs($this->accountant, $this->alpha)
            ->getJson('/api/expenses/form-context')
            ->assertOk()
            ->assertJsonCount(2, 'companies')
            ->assertJsonCount(13, 'categories');
    }

    public function test_expense_routes_are_closed_to_non_mobile_tokens(): void
    {
        Sanctum::actingAs($this->accountant, ['mcp']);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->getJson('/api/expenses')->assertForbidden();
    }
}
