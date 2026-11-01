<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseCategoryProposalData;
use App\Data\Expenses\ExpenseDraftData;
use App\Data\Expenses\ExpenseProposalData;
use App\Data\Expenses\ExpenseRowData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Exceptions\AiUnavailableException;
use RuntimeException;

final class FakeExpenseAnalyzer implements AnalyzesExpenses
{
    public ExpenseDraftData $draft;

    public bool $unavailable = false;

    /** @var list<int> */
    public array $categorizeBatches = [];

    /** @var list<ExpenseAnalysisContextData> */
    public array $contexts = [];

    /** @var list<string> */
    public array $receiptMimes = [];

    /** @var list<string> */
    public array $texts = [];

    public function __construct()
    {
        $this->draft = new ExpenseDraftData(
            date: '2026-10-01',
            amount: '45.50',
            currency: CurrencyEnum::EUR,
            vat_amount: '7.58',
            supplier_name: 'Shell Slovakia',
            description: 'Fuel',
            proposal: new ExpenseProposalData(ExpenseCategoryEnum::Fuel, null, 0.92, 'Fuel station receipt'),
        );
    }

    public function analyzeReceipt(string $bytes, string $mime, ExpenseAnalysisContextData $ctx): ExpenseDraftData
    {
        $this->guard();
        $this->receiptMimes[] = $mime;
        $this->contexts[] = $ctx;

        return $this->draft;
    }

    public function analyzeText(string $text, ExpenseAnalysisContextData $ctx): ExpenseDraftData
    {
        $this->guard();
        $this->texts[] = $text;
        $this->contexts[] = $ctx;

        return $this->draft;
    }

    public function categorize(array $rows, ExpenseAnalysisContextData $ctx): array
    {
        $this->guard();
        $this->categorizeBatches[] = count($rows);

        return array_map(
            static fn (ExpenseRowData $row): ExpenseCategoryProposalData => new ExpenseCategoryProposalData($row->row, ExpenseCategoryEnum::Services, 0.8),
            $rows,
        );
    }

    private function guard(): void
    {
        if ($this->unavailable) {
            throw AiUnavailableException::failed(new RuntimeException('timeout'));
        }
    }
}
