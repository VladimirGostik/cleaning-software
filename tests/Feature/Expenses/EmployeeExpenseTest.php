<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

final class EmployeeExpenseTest extends TestCase
{
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    private TenantMembership $ana;

    private TenantMembership $peter;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->setUpExpenses();

        $this->ana = $this->person($this->alpha, 'Ľudmila', 'Testová');
        $this->peter = $this->person($this->alpha, 'Peter', 'Skúšobný');
    }

    private function person(Tenant $tenant, string $first, string $last): TenantMembership
    {
        return TenantMembership::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => null, 'first_name' => $first, 'last_name' => $last,
        ]);
    }

    private function stage(): string
    {
        /** @var string $uuid */
        $uuid = $this->postJson('/uploads', ['file' => UploadedFile::fake()->createWithContent('payslip.pdf', "%PDF-1.4\npayslip")])->json('uuid');

        return $uuid;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function batch(array $overrides = []): array
    {
        return [
            'date' => '2026-11-05',
            'category' => 'wages',
            'currency' => 'EUR',
            'payroll_month' => '2026-10',
            'lines' => [
                ['tenant_membership_id' => $this->ana->id, 'amount' => '700.10'],
                ['tenant_membership_id' => $this->peter->id, 'amount' => '650.00'],
            ],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function single(array $overrides = []): array
    {
        return $this->expensePayload([
            'category' => 'wages', 'amount' => '500.00', 'vat_amount' => null, 'supplier_name' => null,
            'tenant_membership_id' => $this->ana->id, 'payroll_month' => '2026-10',
            ...$overrides,
        ]);
    }

    private function uploader(): User
    {
        $admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $admin);

        return $admin;
    }

    private function viewerWithoutPersonalData(): User
    {
        $viewer = $this->member($this->alpha, 'Sekretárka');
        $this->grantPermissions($viewer, $this->alpha, [PermissionEnum::ViewExpenses, PermissionEnum::ManageExpenses]);

        return $viewer;
    }

    public function test_a_batch_with_one_pdf_creates_a_confirmed_expense_per_employee_each_with_its_own_receipt(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $uuid = $this->stage();

        $this->post('/expenses/batch', $this->batch(['receipt_uuid' => $uuid]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/expenses');

        $forPeter = Expense::query()->where('tenant_membership_id', $this->peter->id)->firstOrFail();
        $forAna = Expense::query()->where('tenant_membership_id', $this->ana->id)->firstOrFail();

        $this->assertSame(2, Expense::query()->count());
        $this->assertSame('650.00', $forPeter->amount);
        $this->assertSame('700.10', $forAna->amount);

        foreach ([$forPeter, $forAna] as $expense) {
            $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
            $this->assertSame(ExpenseCategoryEnum::Wages, $expense->category);
            $this->assertSame('2026-10', $expense->payroll_month?->format('Y-m'));
            $this->assertFalse($expense->is_undocumented);
            $this->assertNotNull($expense->confirmed_at);
            $this->assertNotNull($expense->getFirstMedia(Expense::RECEIPT_COLLECTION));
        }

        $this->assertNotSame(
            $forPeter->getFirstMedia(Expense::RECEIPT_COLLECTION)?->id,
            $forAna->getFirstMedia(Expense::RECEIPT_COLLECTION)?->id,
        );
        $this->assertSame('Ľudmila Testová', $forAna->supplier_name);
        $this->assertDatabaseCount('temporary_uploads', 0);

        $this->get("/expenses/{$forPeter->id}/receipt")->assertOk();
        $this->get("/expenses/{$forAna->id}/receipt")->assertOk();
        $this->assertTrue(Activity::query()->where('subject_id', $forPeter->id)->exists());
    }

    public function test_deleting_one_expense_keeps_the_receipt_of_the_other(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $this->post('/expenses/batch', $this->batch(['receipt_uuid' => $this->stage()]))->assertSessionHasNoErrors();

        [$first, $second] = Expense::query()->get()->all();
        $first->getFirstMedia(Expense::RECEIPT_COLLECTION)?->delete();

        $this->get("/expenses/{$second->id}/receipt")->assertOk();
    }

    public function test_a_batch_without_a_receipt_works_and_levy_lines_need_no_employee_and_no_duplicate_check(): void
    {
        $this->webAs($this->accountant, $this->alpha);
        $levies = $this->batch([
            'category' => 'payroll_levies',
            'supplier_name' => null,
            'lines' => [
                ['supplier_name' => 'Daňový úrad', 'amount' => '120.00'],
                ['supplier_name' => 'Sociálna poisťovňa', 'amount' => '300.50'],
            ],
        ]);

        $this->post('/expenses/batch', $levies)->assertSessionHasNoErrors();
        $this->post('/expenses/batch', $levies)->assertSessionHasNoErrors();

        $this->assertSame(4, Expense::query()->where('category', 'payroll_levies')->count());
        $this->assertSame(0, Expense::query()->whereNotNull('tenant_membership_id')->count());
        $this->assertSame(0, Expense::query()->whereHas('media')->count());
    }

    public function test_a_levy_batch_accepts_a_header_payroll_month_and_stores_it_on_every_line(): void
    {
        $this->webAs($this->accountant, $this->alpha);

        $this->post('/expenses/batch', $this->batch([
            'category' => 'payroll_levies',
            'lines' => [
                ['supplier_name' => 'Daňový úrad', 'amount' => '120.00'],
                ['supplier_name' => 'Sociálna poisťovňa', 'amount' => '300.50'],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, Expense::query()->whereDate('payroll_month', '2026-10-01')->whereNull('tenant_membership_id')->count());
    }

    public function test_list_items_carry_the_description_as_the_cash_note(): void
    {
        Expense::factory()->undocumented($this->ana)->createdBy($this->accountant)->create(['description' => 'Záloha na materiál']);

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses?filter[undocumented]=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('expenses.data.0.description', 'Záloha na materiál'));
    }

    public function test_a_line_needs_an_employee_or_a_recipient_and_a_payroll_month_when_an_employee_is_named(): void
    {
        $this->webAs($this->accountant, $this->alpha);

        $this->postJson('/expenses/batch', $this->batch(['lines' => [['amount' => '10.00']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.supplier_name');

        $this->postJson('/expenses/batch', $this->batch(['payroll_month' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payroll_month');

        $this->postJson('/expenses/batch', $this->batch(['lines' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines');

        $this->postJson('/expenses/batch', $this->batch(['lines' => [['tenant_membership_id' => $this->ana->id, 'amount' => '0']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.amount');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_employee_of_another_firm_is_refused(): void
    {
        $foreign = $this->person($this->beta, 'Cudzia', 'Zamestnankyňa');
        $this->webAs($this->accountant, $this->alpha);

        $this->postJson('/expenses/batch', $this->batch(['lines' => [['tenant_membership_id' => $foreign->id, 'amount' => '10.00']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lines.0.tenant_membership_id');

        $this->postJson('/expenses', $this->single(['tenant_membership_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_membership_id');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_second_payslip_for_the_same_employee_and_month_asks_and_writes_nothing(): void
    {
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create(['amount' => '555.55']);
        $this->webAs($this->accountant, $this->alpha);

        $response = $this->postJson('/expenses/batch', $this->batch())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duplicate', 'lines.0.tenant_membership_id'])
            ->assertJsonMissingValidationErrors('lines.1.tenant_membership_id');

        $message = data_get($response->json(), ['errors', 'lines.0.tenant_membership_id', 0]);
        $this->assertIsString($message);
        $this->assertStringContainsString('Ľudmila Testová', $message);
        $this->assertStringContainsString('10/2026', $message);
        $this->assertStringContainsString("555,55\u{00A0}€", $message);
        $this->assertDatabaseCount('expenses', 1);

        $this->postJson('/expenses', $this->single())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duplicate', 'tenant_membership_id']);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_keep_both_creates_the_new_payslip_next_to_the_old_one(): void
    {
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create();
        $this->webAs($this->accountant, $this->alpha);

        $this->post('/expenses/batch', $this->batch(['duplicate_action' => 'keep_both']))->assertSessionHasNoErrors();

        $this->assertSame(2, Expense::query()->where('tenant_membership_id', $this->ana->id)->count());
        $this->assertSame(1, Expense::query()->where('tenant_membership_id', $this->peter->id)->count());
    }

    public function test_replace_soft_deletes_the_old_payslip_and_audits_it(): void
    {
        $old = Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create();
        $this->webAs($this->accountant, $this->alpha);

        $this->post('/expenses/batch', $this->batch(['duplicate_action' => 'replace']))->assertSessionHasNoErrors();

        $this->assertSoftDeleted('expenses', ['id' => $old->id]);
        $this->assertSame(1, Expense::query()->where('tenant_membership_id', $this->ana->id)->count());
        $this->assertTrue(Activity::query()->where('subject_id', $old->id)->where('event', 'deleted')->exists());
    }

    public function test_the_same_employee_twice_in_one_batch_is_refused_even_with_keep_both(): void
    {
        $this->webAs($this->accountant, $this->alpha);

        $this->postJson('/expenses/batch', $this->batch([
            'duplicate_action' => 'keep_both',
            'lines' => [
                ['tenant_membership_id' => $this->ana->id, 'amount' => '10.00'],
                ['tenant_membership_id' => $this->ana->id, 'amount' => '20.00'],
            ],
        ]))->assertUnprocessable()->assertJsonValidationErrors('lines.1.tenant_membership_id');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_other_months_other_categories_and_undocumented_rows_are_not_duplicates(): void
    {
        Expense::factory()->forEmployee($this->ana, '2026-09')->createdBy($this->accountant)->create();
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create(['category' => ExpenseCategoryEnum::Services]);
        Expense::factory()->undocumented($this->ana)->createdBy($this->accountant)->create(['payroll_month' => '2026-10-01']);
        $this->webAs($this->accountant, $this->alpha);

        $this->post('/expenses/batch', $this->batch())->assertSessionHasNoErrors();

        $this->assertSame(5, Expense::query()->where('tenant_membership_id', $this->ana->id)->count() + 1);
    }

    public function test_updating_a_payslip_does_not_conflict_with_itself_but_conflicts_with_another(): void
    {
        $mine = Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create();
        $this->webAs($this->accountant, $this->alpha);

        $this->put("/expenses/{$mine->id}", $this->single(['amount' => '510.00']))->assertSessionHasNoErrors();
        $this->assertSame('510.00', $mine->fresh()?->amount);

        $other = Expense::factory()->forEmployee($this->ana, '2026-09')->createdBy($this->accountant)->create();

        $this->putJson("/expenses/{$other->id}", $this->single())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duplicate');
    }

    public function test_a_put_without_the_employee_fields_keeps_the_employee_link(): void
    {
        $expense = Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create(['amount' => '500.00']);
        $this->webAs($this->accountant, $this->alpha);

        $this->put("/expenses/{$expense->id}", $this->expensePayload(['category' => 'wages', 'amount' => '520.00', 'vat_amount' => null]))
            ->assertSessionHasNoErrors();

        $fresh = $expense->refresh();
        $this->assertSame($this->ana->id, $fresh->tenant_membership_id);
        $this->assertSame('2026-10', $fresh->payroll_month?->format('Y-m'));
        $this->assertSame('520.00', $fresh->amount);
        $this->assertSame(ExpenseStatusEnum::Confirmed, $fresh->status);

        $this->put("/expenses/{$expense->id}", $this->expensePayload(['category' => 'wages', 'tenant_membership_id' => null, 'payroll_month' => null, 'vat_amount' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull($expense->fresh()?->tenant_membership_id);
    }

    public function test_a_plain_edit_after_keep_both_does_not_ask_about_the_duplicate_again(): void
    {
        $this->webAs($this->accountant, $this->alpha);
        $this->post('/expenses', $this->single())->assertSessionHasNoErrors();
        $this->post('/expenses', $this->single(['duplicate_action' => 'keep_both']))->assertSessionHasNoErrors();

        $second = Expense::query()->latest('created_at')->firstOrFail();

        $this->put("/expenses/{$second->id}", $this->single(['description' => 'Doplnená poznámka']))->assertSessionHasNoErrors();

        $this->assertSame('Doplnená poznámka', $second->fresh()?->description);
        $this->assertSame(2, Expense::query()->where('tenant_membership_id', $this->ana->id)->count());
    }

    public function test_employee_linked_expenses_need_a_category_and_a_month_and_are_always_confirmed(): void
    {
        $this->webAs($this->accountant, $this->alpha);

        $this->postJson('/expenses', $this->single(['category' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson('/expenses', $this->single(['payroll_month' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('payroll_month');
        $this->postJson('/expenses', $this->single(['payroll_month' => '2026-13']))
            ->assertUnprocessable()->assertJsonValidationErrors('payroll_month');

        $this->post('/expenses', $this->single(['status' => 'draft']))->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
        $this->assertNotNull($expense->confirmed_at);
    }

    public function test_undocumented_cash_needs_an_employee_refuses_a_receipt_and_accepts_string_booleans(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $cash = $this->single(['is_undocumented' => '1', 'payroll_month' => null, 'description' => 'Hotovosť na materiál']);

        $this->postJson('/expenses', [...$cash, 'tenant_membership_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('tenant_membership_id');

        $this->postJson('/expenses', [...$cash, 'receipt_uuid' => $this->stage()])
            ->assertUnprocessable()->assertJsonValidationErrors('receipt_uuid');

        $this->post('/expenses', $cash)->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $this->assertTrue($expense->is_undocumented);
        $this->assertNull($expense->payroll_month);
        $this->assertSame($this->ana->id, $expense->tenant_membership_id);
        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
        $this->assertSame('Hotovosť na materiál', $expense->description);

        $this->post('/expenses', [...$cash, 'is_undocumented' => '0', 'payroll_month' => '2026-10'])->assertSessionHasNoErrors();
        $this->assertFalse(Expense::query()->where('payroll_month', '2026-10-01')->sole()->is_undocumented);
    }

    public function test_the_database_refuses_an_undocumented_row_without_an_employee(): void
    {
        $this->expectException(QueryException::class);

        Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create(['is_undocumented' => true]);
    }

    public function test_the_default_list_hides_cash_and_has_its_own_totals_per_tab(): void
    {
        Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create(['amount' => '100.00']);
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create(['amount' => '200.00']);
        Expense::factory()->undocumented($this->ana)->createdBy($this->accountant)->create(['amount' => '30.00']);
        Expense::factory()->undocumented($this->peter)->createdBy($this->accountant)->create(['amount' => '12.50']);

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 2)
                ->where('totals.0.currency', 'EUR')
                ->where('totals.0.amount', '300.00')
                ->where('totals.0.count', 2));

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses?filter[undocumented]=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 2)
                ->where('expenses.data.0.is_undocumented', true)
                ->where('totals.0.amount', '42.50')
                ->where('totals.0.count', 2));

        $this->webAs($this->accountant, $this->alpha)
            ->get("/expenses?filter[tenant_membership_id]={$this->ana->id}&filter[payroll_month]=2026-10")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.employee_name', 'Ľudmila Testová')
                ->where('expenses.data.0.payroll_month', '2026-10')
                ->where('totals.0.amount', '200.00'));
    }

    public function test_totals_are_grouped_per_currency_over_all_pages(): void
    {
        Expense::factory()->count(3)->forTenant($this->alpha)->createdBy($this->accountant)->create(['amount' => '10.00']);
        Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create(['amount' => '5.00', 'currency' => 'CZK']);

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses?per_page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 2)
                ->has('totals', 2)
                ->where('totals.0.currency', 'CZK')
                ->where('totals.0.amount', '5.00')
                ->where('totals.1.currency', 'EUR')
                ->where('totals.1.amount', '30.00')
                ->where('totals.1.count', 3));
    }

    public function test_without_personal_data_employee_expenses_are_hidden_and_closed(): void
    {
        $plain = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        $payslip = Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create();
        $cash = Expense::factory()->undocumented($this->ana)->createdBy($this->accountant)->create();
        $viewer = $this->viewerWithoutPersonalData();

        $this->webAs($viewer, $this->alpha)
            ->get('/expenses')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('expenses.data', 1)
                ->where('expenses.data.0.id', $plain->id)
                ->has('context.employees', 0)
                ->where('totals.0.count', 1));

        $this->webAs($viewer, $this->alpha)->get('/expenses?filter[undocumented]=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('expenses.data', 0));

        foreach ([$payslip, $cash] as $expense) {
            $this->webAs($viewer, $this->alpha)->get("/expenses/{$expense->id}")->assertForbidden();
            $this->webAs($viewer, $this->alpha)->get("/expenses/{$expense->id}/receipt")->assertForbidden();
            $this->webAs($viewer, $this->alpha)->delete("/expenses/{$expense->id}")->assertForbidden();
            $this->webAs($viewer, $this->alpha)->put("/expenses/{$expense->id}", $this->single())->assertForbidden();
        }

        $this->webAs($viewer, $this->alpha)->get("/expenses/{$plain->id}")->assertOk();
        $this->webAs($viewer, $this->alpha)->postJson('/expenses', $this->single())->assertForbidden();
        $this->webAs($viewer, $this->alpha)->postJson('/expenses/batch', $this->batch())->assertForbidden();
        $this->assertDatabaseCount('expenses', 3);

        $this->webAs($viewer, $this->alpha)->postJson('/expenses/batch', $this->batch([
            'category' => 'payroll_levies',
            'lines' => [['supplier_name' => 'Daňový úrad', 'amount' => '10.00']],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expenses', 4);
    }

    public function test_the_api_list_hides_employee_expenses_without_personal_data(): void
    {
        $plain = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create();
        $viewer = $this->viewerWithoutPersonalData();
        $this->grantPermissions($viewer, $this->alpha, [PermissionEnum::AccessMobileApp]);

        $this->apiAs($viewer, $this->alpha)->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $plain->id);

        $this->apiAs($this->accountant, $this->alpha)->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->apiAs($viewer, $this->alpha)->getJson('/api/expenses?scope=all')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_only_admin_and_accountant_may_record_payslips(): void
    {
        $admin = $this->member($this->alpha, 'Admin');
        $manager = $this->member($this->alpha, 'Vedúca');
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');

        $this->webAs($admin, $this->alpha)->post('/expenses/batch', $this->batch())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('expenses', 2);

        foreach ([$manager, $cleaner] as $user) {
            $this->webAs($user, $this->alpha)->postJson('/expenses/batch', $this->batch(['payroll_month' => '2026-09']))->assertForbidden();
        }

        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_the_form_context_lists_employees_including_inactive_ones(): void
    {
        $this->peter->update(['is_active' => false]);
        $this->person($this->beta, 'Beta', 'Zamestnanec');

        $this->webAs($this->accountant, $this->alpha)
            ->get('/expenses')
            ->assertInertia(function (AssertableInertia $page): void {
                /** @var array{context: array{employees: list<array{label: string, is_active: bool}>}} $props */
                $props = $page->toArray()['props'];
                $labels = array_column($props['context']['employees'], 'is_active', 'label');

                $this->assertCount(5, $props['context']['employees']);
                $this->assertFalse($labels['Peter Skúšobný']);
                $this->assertTrue($labels['Ľudmila Testová']);
                $this->assertTrue($labels['Beta Zamestnanec']);
            });
    }

    public function test_the_employee_page_lists_that_persons_expenses_for_permitted_users_only(): void
    {
        Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->accountant)->create(['amount' => '700.00']);
        Expense::factory()->undocumented($this->ana)->createdBy($this->accountant)->create(['amount' => '20.00']);
        Expense::factory()->forEmployee($this->peter, '2026-10')->createdBy($this->accountant)->create();

        $this->webAs($this->member($this->alpha, 'Admin'), $this->alpha)
            ->get("/employees/{$this->ana->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Employees/Show')
                ->has('expenses', 2));

        $secretary = $this->member($this->alpha, 'Sekretárka');
        $this->grantPermissions($secretary, $this->alpha, [PermissionEnum::ViewEmployees, PermissionEnum::ViewExpenses]);

        $this->webAs($secretary, $this->alpha)
            ->get("/employees/{$this->ana->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('expenses', null));
    }

    public function test_receipt_analysis_text_without_lines_and_plain_expenses_behave_as_before(): void
    {
        $this->webAs($this->accountant, $this->alpha)
            ->post('/expenses', $this->expensePayload())
            ->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();

        $this->assertNull($expense->tenant_membership_id);
        $this->assertNull($expense->payroll_month);
        $this->assertFalse($expense->is_undocumented);
        $this->assertSame(0, Expense::query()->whereNotNull('tenant_membership_id')->count());
    }
}
