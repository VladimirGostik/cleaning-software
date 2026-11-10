<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class McpEmployeeExpenseToolsTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $admin;

    private string $token;

    private TenantMembership $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->token = $this->mcpToken($this->admin);
        $this->ana = $this->person('Ľudmila', 'Testová');
    }

    private function person(string $first, string $last): TenantMembership
    {
        return TenantMembership::factory()->create([
            'tenant_id' => $this->alpha->id, 'user_id' => null, 'first_name' => $first, 'last_name' => $last,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payslip(array $overrides = []): array
    {
        return [
            'company_id' => $this->alpha->id,
            'date' => '2026-11-05',
            'amount' => '700.10',
            'category' => 'wages',
            'employee_name' => 'Testova Ludmila',
            'payroll_month' => '2026-10',
            ...$overrides,
        ];
    }

    private function viewerWithoutPersonalData(): User
    {
        $viewer = $this->member($this->alpha, 'Sekretárka');
        $this->grant($viewer, $this->alpha, PermissionEnum::ViewExpenses->value, PermissionEnum::ManageExpenses->value);

        return $viewer;
    }

    public function test_create_expense_with_employee_name_creates_a_linked_confirmed_expense(): void
    {
        $result = $this->callTool($this->token, 'create_expense', $this->payslip());

        $this->assertFalse($result->isError, $result->text);

        $expense = Expense::query()->sole();
        $this->assertSame($this->ana->id, $expense->tenant_membership_id);
        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
        $this->assertSame(ExpenseCategoryEnum::Wages, $expense->category);
        $this->assertSame('2026-10', $expense->payroll_month?->format('Y-m'));
        $this->assertSame('Ľudmila Testová', $result->string('employee_name'));
        $this->assertSame('2026-10', $result->string('payroll_month'));
    }

    public function test_create_expense_by_employee_id_and_undocumented_cash(): void
    {
        $result = $this->callTool($this->token, 'create_expense', [
            'company_id' => $this->alpha->id, 'date' => '2026-11-05', 'amount' => '30.00', 'category' => 'wages',
            'employee_id' => $this->ana->id, 'is_undocumented' => true, 'description' => 'Hotovosť',
        ]);

        $this->assertFalse($result->isError, $result->text);
        $this->assertTrue(Expense::query()->sole()->is_undocumented);
        $this->assertTrue($result->bool('is_undocumented'));
    }

    public function test_an_unknown_or_ambiguous_name_is_a_tool_error_and_writes_nothing(): void
    {
        $unknown = $this->callTool($this->token, 'create_expense', $this->payslip(['employee_name' => 'Niekto Neznamy']));
        $this->assertTrue($unknown->isError);
        $this->assertStringContainsString('nenašiel', $unknown->text);

        $this->person('Jana', 'Dvojitá');
        $this->person('Jana', 'Dvojitá');

        $ambiguous = $this->callTool($this->token, 'create_expense', $this->payslip(['employee_name' => 'Dvojita Jana']));
        $this->assertTrue($ambiguous->isError);
        $this->assertStringContainsString('employee_id', $ambiguous->text);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_second_payslip_for_the_month_needs_keep_both_and_replace_is_refused(): void
    {
        $this->assertFalse($this->callTool($this->token, 'create_expense', $this->payslip())->isError);

        $duplicate = $this->callTool($this->token, 'create_expense', $this->payslip());
        $this->assertTrue($duplicate->isError);
        $this->assertStringContainsString('10/2026', $duplicate->text);
        $this->assertDatabaseCount('expenses', 1);

        $replace = $this->callTool($this->token, 'create_expense', $this->payslip(['duplicate_action' => 'replace']));
        $this->assertTrue($replace->isError);
        $this->assertDatabaseCount('expenses', 1);

        $keep = $this->callTool($this->token, 'create_expense', $this->payslip(['duplicate_action' => 'keep_both']));
        $this->assertFalse($keep->isError, $keep->text);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_the_schema_offers_only_keep_both(): void
    {
        $tools = array_column((array) $this->rpc($this->token, 'tools/list', ['per_page' => 100])->json('result.tools'), null, 'name');

        foreach (['create_expense', 'update_expense'] as $name) {
            $this->assertSame(['keep_both'], data_get($tools, "{$name}.inputSchema.properties.duplicate_action.enum"));
        }
    }

    public function test_employee_fields_need_the_personal_data_permission(): void
    {
        $viewer = $this->viewerWithoutPersonalData();
        $token = $this->mcpToken($viewer);

        $denied = $this->callTool($token, 'create_expense', $this->payslip());
        $this->assertTrue($denied->isError);

        $byId = $this->callTool($token, 'create_expense', $this->payslip(['employee_name' => null, 'employee_id' => $this->ana->id]));
        $this->assertTrue($byId->isError);

        $plain = $this->callTool($token, 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-11-05', 'amount' => '5.00']);
        $this->assertFalse($plain->isError, $plain->text);
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_list_expenses_hides_employee_rows_without_personal_data_and_filters_for_those_who_have_it(): void
    {
        $plain = Expense::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
        $payslip = Expense::factory()->forEmployee($this->ana, '2026-10')->createdBy($this->admin)->create();
        $cash = Expense::factory()->undocumented($this->ana)->createdBy($this->admin)->create();

        $viewer = $this->viewerWithoutPersonalData();
        $hidden = $this->callTool($this->mcpToken($viewer), 'list_expenses', ['company_id' => $this->alpha->id]);
        $this->assertSame([$plain->id], $hidden->ids());

        $default = $this->callTool($this->token, 'list_expenses', ['company_id' => $this->alpha->id]);
        $this->assertEqualsCanonicalizing([$plain->id, $payslip->id], $default->ids());

        $row = collect($default->rows())->firstWhere('id', $payslip->id);
        $this->assertSame('Ľudmila Testová', $row['employee_name'] ?? null);
        $this->assertSame('2026-10', $row['payroll_month'] ?? null);
        $this->assertFalse($row['is_undocumented'] ?? true);

        $onlyCash = $this->callTool($this->token, 'list_expenses', ['company_id' => $this->alpha->id, 'undocumented' => true]);
        $this->assertFalse($onlyCash->isError, $onlyCash->text);
        $this->assertSame([$cash->id], $onlyCash->ids());
        $this->assertTrue($onlyCash->rows()[0]['is_undocumented'] ?? false);

        $byEmployee = $this->callTool($this->token, 'list_expenses', ['company_id' => $this->alpha->id, 'employee_id' => $this->ana->id, 'payroll_month' => '2026-10']);
        $this->assertSame([$payslip->id], $byEmployee->ids());

        $badMonth = $this->callTool($this->token, 'list_expenses', ['company_id' => $this->alpha->id, 'payroll_month' => '10/2026']);
        $this->assertTrue($badMonth->isError);
    }

    public function test_update_expense_keeps_the_employee_and_can_move_the_month_but_not_without_permission(): void
    {
        $created = $this->callTool($this->token, 'create_expense', $this->payslip());
        $id = $created->string('id');

        $moved = $this->callTool($this->token, 'update_expense', ['company_id' => $this->alpha->id, 'expense_id' => $id, 'payroll_month' => '2026-09', 'amount' => '710.00']);
        $this->assertFalse($moved->isError, $moved->text);

        $expense = Expense::query()->findOrFail($id);
        $this->assertSame($this->ana->id, $expense->tenant_membership_id);
        $this->assertSame('2026-09', $expense->payroll_month?->format('Y-m'));
        $this->assertSame('710.00', $expense->amount);

        $denied = $this->callTool($this->mcpToken($this->viewerWithoutPersonalData()), 'update_expense', ['company_id' => $this->alpha->id, 'expense_id' => $id, 'amount' => '1.00']);
        $this->assertTrue($denied->isError);
        $this->assertSame('710.00', Expense::query()->findOrFail($id)->amount);
    }

    public function test_the_audit_keeps_no_employee_name(): void
    {
        $this->callTool($this->token, 'create_expense', $this->payslip());

        $audit = $this->aiActivities()->sole();

        $this->assertSame('tool.create_expense', $audit->description);
        $this->assertStringNotContainsString('Testova', (string) json_encode($audit->properties));
    }
}
