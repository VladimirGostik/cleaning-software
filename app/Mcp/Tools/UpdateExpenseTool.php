<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Models\Expense;
use App\Models\User;
use App\Services\Expenses\EmployeeExpenseService;
use App\Services\ExpenseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_expense')]
#[Description('Changes fields of an expense. Omitted fields stay as they are. Setting a category on a draft confirms it.')]
#[IsDestructive]
final class UpdateExpenseTool extends TenantTool
{
    use DescribesExpenseEmployee;

    protected function rules(): array
    {
        return ['expense_id' => ['required', 'uuid'], ...$this->employeeRules()];
    }

    public function execute(Request $request, User $user, string $companyId, ExpenseService $expenses, EmployeeExpenseService $employees): Response
    {
        $expense = Expense::query()->findOrFail($this->id($request, 'expense_id'));

        Gate::forUser($user)->authorize('update', $expense);

        $employeeId = $this->resolveEmployeeId($request, $user, $companyId, $employees) ?? $expense->tenant_membership_id;

        $validated = ExpenseUpsertData::validateAndCreate([
            'date' => $request->get('date', $expense->date->toDateString()),
            'amount' => $request->get('amount', $expense->amount),
            'currency' => $request->get('currency', $expense->currency->value),
            'vat_amount' => $request->has('vat_amount') ? $request->get('vat_amount') : $expense->vat_amount,
            'supplier_name' => $request->has('supplier_name') ? $request->get('supplier_name') : $expense->supplier_name,
            'description' => $request->has('description') ? $request->get('description') : $expense->description,
            'category' => $request->get('category', $expense->category?->value),
            'status' => $request->get('status'),
            'tenant_id' => $companyId,
            'tenant_membership_id' => $employeeId,
            'payroll_month' => $request->has('payroll_month') ? $request->get('payroll_month') : $expense->payroll_month?->format('Y-m'),
            'is_undocumented' => $request->has('is_undocumented') ? (bool) $request->get('is_undocumented') : $expense->is_undocumented,
            'duplicate_action' => $request->get('duplicate_action'),
        ]);

        $data = ExpenseUpsertData::from([...$validated->toArray(), 'source' => $expense->source->value]);

        return $this->json($expenses->detail($expenses->update($expense, $data, $user, ''), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'expense_id' => $schema->string()->format('uuid')->description('Expense id from list_expenses.')->required(),
            'date' => $schema->string()->format('date')->description('Expense date, YYYY-MM-DD.'),
            'amount' => $schema->string()->description('Gross amount, decimal with at most 2 places.'),
            'currency' => $schema->string()->enum(array_map(fn (CurrencyEnum $case): string => $case->value, CurrencyEnum::cases()))->description('Currency.'),
            'vat_amount' => $schema->string()->nullable()->description('VAT part of the amount; null clears it.'),
            'supplier_name' => $schema->string()->max(200)->nullable()->description('Who was paid; null clears it.'),
            'description' => $schema->string()->max(5000)->nullable()->description('What it was for; null clears it.'),
            'category' => $schema->string()->enum(array_map(fn (ExpenseCategoryEnum $case): string => $case->value, ExpenseCategoryEnum::cases()))->description('Category.'),
            ...$this->employeeProperties($schema),
        ];
    }
}
