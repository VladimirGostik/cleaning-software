<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Expenses\ExpenseStatementRowsData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;

trait DescribesBankExpenseRows
{
    protected function rowsData(Request $request): ExpenseStatementRowsData
    {
        $input = ['account_iban' => $request->get('account_iban'), 'rows' => $request->get('rows')];

        foreach (['include_references', 'exclude_references'] as $key) {
            if ($request->get($key) !== null) {
                $input[$key] = $request->get($key);
            }
        }

        return ExpenseStatementRowsData::validateAndCreate($input);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function auditedArguments(array $arguments): array
    {
        $count = static fn (string $key): int => is_array($arguments[$key] ?? null) ? count($arguments[$key]) : 0;

        return [
            'company_id' => $arguments['company_id'] ?? null,
            'rows_count' => $count('rows'),
            'include_count' => $count('include_references'),
            'exclude_count' => $count('exclude_references'),
        ];
    }

    /** @return array<string, Type> */
    protected function rowsSchema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'account_iban' => $schema->string()->max(40)->description('IBAN of the account the statement belongs to, from the statement header (Acct/Id/IBAN).')->required(),
            'rows' => $schema->array()->items($schema->object([
                'reference' => $schema->string()->max(100)->description('NtryRef exactly as written in the statement: letters, digits and . _ : / + - only.')->required(),
                'date' => $schema->string()->format('date')->description('Booking date (BookgDt), YYYY-MM-DD.')->required(),
                'amount' => $schema->string()->description('Amount (Amt) as a decimal string with at most 2 places, for example "42.50".')->required(),
                'direction' => $schema->string()->enum(['DBIT'])->description('CdtDbtInd of the entry. Only DBIT (outgoing) is accepted.')->required(),
                'currency' => $schema->string()->enum(['EUR'])->description('Ccy of the amount. Only EUR is accepted.')->required(),
                'counterparty' => $schema->string()->max(200)->nullable()->description('Creditor name. Leave empty rather than guess.'),
                'note' => $schema->string()->max(500)->nullable()->description('Remittance text. Leave empty rather than guess.'),
            ]))->description('Outgoing (DBIT) booked entries only, at most 100. Send every debit row of the statement in one call when you can. Never send incoming payments, never invent or repair a row.')->required(),
        ];
    }
}
