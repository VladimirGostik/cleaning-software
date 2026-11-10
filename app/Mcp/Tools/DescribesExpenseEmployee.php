<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ExpenseDuplicateActionEnum;
use App\Models\Expense;
use App\Models\User;
use App\Services\Expenses\EmployeeExpenseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

trait DescribesExpenseEmployee
{
    /** @return array<string, Type> */
    protected function employeeProperties(JsonSchema $schema): array
    {
        return [
            'employee_id' => $schema->string()->format('uuid')->description('Employee (membership) id the expense belongs to, for payslips and cash given to an employee. Needs the employee personal data permission.'),
            'employee_name' => $schema->string()->max(200)->description('Employee name as printed, used when employee_id is unknown; "First Last" and "Last First" both match. An unknown or ambiguous name is an error.'),
            'payroll_month' => $schema->string()->description('Payroll month YYYY-MM. Required with an employee unless is_undocumented. An employee expense needs a category and is always confirmed.'),
            'is_undocumented' => $schema->boolean()->description('Cash given to the employee with no document: needs an employee, no payroll month required.'),
            'duplicate_action' => $schema->string()->enum([ExpenseDuplicateActionEnum::KeepBoth->value])->description('Set to keep_both to save a second payslip for the same employee and month. Replacing an expense is not possible here.'),
        ];
    }

    /** @return array<string, mixed> */
    protected function employeeRules(): array
    {
        return [
            'employee_id' => ['sometimes', 'nullable', 'uuid'],
            'employee_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'duplicate_action' => ['sometimes', 'nullable', 'in:'.ExpenseDuplicateActionEnum::KeepBoth->value],
        ];
    }

    /** Names are people's data: the audit keeps the id and the month, not the name. */
    protected function auditedArguments(array $arguments): array
    {
        unset($arguments['employee_name']);

        return $arguments;
    }

    protected function resolveEmployeeId(Request $request, User $user, string $companyId, EmployeeExpenseService $employees): ?string
    {
        $id = $request->get('employee_id');
        $name = $request->get('employee_name');

        if (! is_string($id) && ! is_string($name)) {
            return null;
        }

        Gate::forUser($user)->authorize('createForEmployee', [Expense::class, $companyId]);

        if (is_string($id) && $id !== '') {
            return $employees->assertMembershipInTenant($id, $companyId, 'employee_id')->id;
        }

        return is_string($name) && $name !== '' ? $employees->resolveByName($name, $companyId)->id : null;
    }
}
