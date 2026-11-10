<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\User;
use App\Services\Expenses\EmployeeExpenseService;
use App\Services\ExpenseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_expense')]
#[Description('Records an expense from fields you extracted yourself (no file upload). Without a category it is saved as a draft for later review; with one it is confirmed. For a payslip or cash given to an employee pass the employee, the payroll month and a category; a second payslip for the same employee and month is refused unless duplicate_action is keep_both.')]
#[IsDestructive(false)]
final class CreateExpenseTool extends TenantTool
{
    use DescribesExpenseEmployee;

    protected function rules(): array
    {
        return $this->employeeRules();
    }

    public function execute(Request $request, User $user, string $companyId, ExpenseService $expenses, EmployeeExpenseService $employees): Response
    {
        $employeeId = $this->resolveEmployeeId($request, $user, $companyId, $employees);

        $validated = ExpenseUpsertData::validateAndCreate([
            'date' => $request->get('date'),
            'amount' => $request->get('amount'),
            'currency' => $request->get('currency', CurrencyEnum::EUR->value),
            'vat_amount' => $request->get('vat_amount'),
            'supplier_name' => $request->get('supplier_name'),
            'description' => $request->get('description'),
            'category' => $request->get('category'),
            'status' => $request->get('status'),
            'tenant_id' => $companyId,
            'tenant_membership_id' => $employeeId,
            'payroll_month' => $request->get('payroll_month'),
            'is_undocumented' => (bool) $request->get('is_undocumented', false),
            'duplicate_action' => $request->get('duplicate_action'),
        ]);

        $data = ExpenseUpsertData::from([...$validated->toArray(), 'source' => ExpenseSourceEnum::Mcp->value]);

        $expenses->assertMayCreate($data, $user);

        return $this->json($expenses->detail($expenses->create($data, $user, ''), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'date' => $schema->string()->format('date')->description('Expense date, YYYY-MM-DD.')->required(),
            'amount' => $schema->string()->description('Gross amount as a decimal with at most 2 places, for example "42.50".')->required(),
            'currency' => $schema->string()->enum(array_map(fn (CurrencyEnum $case): string => $case->value, CurrencyEnum::cases()))->description('Defaults to EUR.'),
            'vat_amount' => $schema->string()->nullable()->description('VAT part of the amount, at most the amount.'),
            'supplier_name' => $schema->string()->max(200)->description('Who was paid.'),
            'description' => $schema->string()->max(5000)->description('What it was for.'),
            'category' => $schema->string()->enum(array_map(fn (ExpenseCategoryEnum $case): string => $case->value, ExpenseCategoryEnum::cases()))->description('Leave out when unsure: the expense then stays a draft.'),
            'status' => $schema->string()->enum(array_map(fn (ExpenseStatusEnum $case): string => $case->value, ExpenseStatusEnum::cases()))->description('Defaults to confirmed when a category is given, else draft.'),
            ...$this->employeeProperties($schema),
        ];
    }
}
