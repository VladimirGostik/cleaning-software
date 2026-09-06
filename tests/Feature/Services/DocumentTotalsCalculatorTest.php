<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RoundingModeEnum;
use App\Services\DocumentTotalsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentTotalsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private DocumentTotalsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DocumentTotalsCalculator;
    }

    public function test_line_applies_discount_before_vat(): void
    {
        $line = $this->calculator->line(2, 100, 10, 23, true);

        $this->assertSame(180.0, $line['line_base']);
        $this->assertSame(41.4, $line['line_vat']);
        $this->assertSame(221.4, $line['line_total']);
    }

    public function test_line_forces_zero_vat_for_non_vat_payer(): void
    {
        $line = $this->calculator->line(1, 100, 0, 23, false);

        $this->assertSame(100.0, $line['line_base']);
        $this->assertSame(0.0, $line['line_vat']);
        $this->assertSame(100.0, $line['line_total']);
    }

    public function test_totals_breakdown_grouped_and_sorted_rate_desc(): void
    {
        $lines = [
            ['vat_rate' => 5, 'line_base' => 50, 'line_vat' => 2.5, 'line_total' => 52.5],
            ['vat_rate' => 23, 'line_base' => 100, 'line_vat' => 23, 'line_total' => 123],
        ];

        $totals = $this->calculator->totals($lines, true);

        $this->assertSame(150.0, $totals['subtotal']);
        $this->assertSame(25.5, $totals['vat_amount']);
        $this->assertSame(175.5, $totals['total']);
        $this->assertNotNull($totals['vat_breakdown']);
        $this->assertSame(23.0, $totals['vat_breakdown'][0]['rate']);
        $this->assertSame(5.0, $totals['vat_breakdown'][1]['rate']);
    }

    public function test_totals_breakdown_null_for_non_vat_payer(): void
    {
        $lines = [
            ['vat_rate' => 0, 'line_base' => 100, 'line_vat' => 0, 'line_total' => 100],
        ];

        $totals = $this->calculator->totals($lines, false);

        $this->assertNull($totals['vat_breakdown']);
    }

    public function test_cash005_rounding_produces_rounding_amount(): void
    {
        $lines = [
            ['vat_rate' => 0, 'line_base' => 100.02, 'line_vat' => 0, 'line_total' => 100.02],
        ];

        $totals = $this->calculator->totals($lines, false, RoundingModeEnum::Cash005);

        $this->assertSame(100.0, $totals['total']);
        $this->assertSame(-0.02, $totals['rounding_amount']);
    }
}
