<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Data\Expenses\BankCsvMappingData;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseCategoryProposalData;
use App\Data\Expenses\ExpenseDraftData;
use App\Data\Expenses\ExpenseRowData;
use App\Exceptions\AiUnavailableException;

interface AnalyzesExpenses
{
    /** @throws AiUnavailableException */
    public function analyzeReceipt(string $bytes, string $mime, ExpenseAnalysisContextData $ctx): ExpenseDraftData;

    /** @throws AiUnavailableException */
    public function analyzeText(string $text, ExpenseAnalysisContextData $ctx): ExpenseDraftData;

    /**
     * @param  list<string>  $headerRow
     * @param  list<list<string>>  $sampleRows
     *
     * @throws AiUnavailableException
     */
    public function mapBankCsv(array $headerRow, array $sampleRows): BankCsvMappingData;

    /**
     * @param  list<ExpenseRowData>  $rows
     * @return list<ExpenseCategoryProposalData>
     *
     * @throws AiUnavailableException
     */
    public function categorize(array $rows, ExpenseAnalysisContextData $ctx): array;
}
