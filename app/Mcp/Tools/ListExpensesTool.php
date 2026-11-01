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
#[Description('Pages through the expenses of one company. Filter by search (supplier or description), status or category.')]
#[IsReadOnly]
final class ListExpensesTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'status', 'category', 'source'];
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
            ...$this->pagingProperties($schema),
        ];
    }
}
