<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Expense;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_expenses')]
#[Description('Pages through the expenses of one company. Filter by search (supplier or description), status, category, employee, payroll month or undocumented cash (hidden unless undocumented is true). Employee expenses need the employee personal data permission.')]
#[IsReadOnly]
final class ListExpensesTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'category', 'source', 'employee_id', 'payroll_month', 'undocumented'];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            ...parent::rules(),
            'payroll_month' => ['sometimes', 'string', 'date_format:Y-m'],
            'undocumented' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function requestInput(array $arguments): array
    {
        $input = parent::requestInput($arguments);
        $filters = is_array($input['filter'] ?? null) ? $input['filter'] : [];

        if (isset($filters['employee_id'])) {
            $filters['tenant_membership_id'] = $filters['employee_id'];
            unset($filters['employee_id']);
        }

        return $filters === [] ? $input : [...$input, 'filter' => $filters];
    }

    public function execute(User $user, ExpenseService $expenses): Response
    {
        Gate::forUser($user)->authorize('viewAny', Expense::class);

        return $this->json($this->pageOf($expenses->paginate(request())));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Supplier or description contains.'),
            'status' => $schema->string()->enum(['draft', 'confirmed'])->description('Expense status.'),
            'category' => $schema->string()->description('Expense category.'),
            'source' => $schema->string()->description('Expense source.'),
            'employee_id' => $schema->string()->format('uuid')->description('Only expenses of this employee.'),
            'payroll_month' => $schema->string()->description('Only expenses of this payroll month, YYYY-MM.'),
            'undocumented' => $schema->boolean()->description('true lists only cash given without a document; by default those are left out.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
