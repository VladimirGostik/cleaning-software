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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('import_bank_expenses')]
#[Description('Creates draft expenses from outgoing payments you read from a bank statement. The same reference on the same account is never created twice, also across the web import. States are computed again here: rows with state new are created, possible_match and previously_deleted only when listed in include_references, exclude_references wins. Only call after the user confirmed the preview_bank_expenses result. The server cannot check that the rows match the file: never invent, repair or guess a row, and if you cannot read the file exactly or an entry has no NtryRef, ask the user to drag the file into the chat or use the web import page (Výdavky, Import).')]
#[IsDestructive(false)]
final class ImportBankExpensesTool extends TenantTool
{
    use DescribesBankExpenseRows;

    public function execute(Request $request, User $user, BankExpenseRowsService $rows): Response
    {
        Gate::forUser($user)->authorize('import', Expense::class);

        $result = $rows->import($this->rowsData($request), $user);

        $this->auditExtra = [
            'created' => $result->created,
            'already_imported' => $result->already_imported,
            'excluded' => $result->excluded,
            'text_withheld' => count($result->text_withheld),
        ];

        return $this->json($result);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function scannedArguments(array $arguments): array
    {
        $rows = $arguments['rows'] ?? null;

        if (! is_array($rows)) {
            return $arguments;
        }

        $arguments['rows'] = array_map(static function (mixed $row): mixed {
            if (! is_array($row)) {
                return $row;
            }

            unset($row['counterparty'], $row['note']);

            return $row;
        }, $rows);

        return $arguments;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->rowsSchema($schema),
            'include_references' => $schema->array()->items($schema->string())->description('References of possible_match or previously_deleted rows the user chose to import.'),
            'exclude_references' => $schema->array()->items($schema->string())->description('References of rows not to import. Wins over include_references.'),
        ];
    }
}
