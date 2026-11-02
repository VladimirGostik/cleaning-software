<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Expense;
use App\Models\User;
use App\Services\Expenses\BankExpenseRowsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('preview_bank_expenses')]
#[Description('Shows what import_bank_expenses would do with outgoing payments you read from a bank statement (camt.053): per row new, already_imported, previously_deleted or possible_match, plus the exact total of the sent rows. Creates nothing. Show the user the rows and the total, compare the total with the statement, and wait for their yes before importing. There is no file upload; if you cannot read the file exactly or an entry has no NtryRef, ask the user to drag the file into the chat or use the web import page (Výdavky, Import).')]
#[IsReadOnly]
final class PreviewBankExpensesTool extends TenantTool
{
    use DescribesBankExpenseRows;

    public function execute(Request $request, User $user, BankExpenseRowsService $rows): Response
    {
        Gate::forUser($user)->authorize('import', Expense::class);

        return $this->json($rows->preview($this->rowsData($request), $user));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return $this->rowsSchema($schema);
    }
}
