<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoundingModeEnum;
use App\Models\InvoiceItem;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\Data;

final readonly class DocumentItemService
{
    public function __construct(private DocumentTotalsCalculator $totals) {}

    /**
     * @template TItem of Model
     * @template TParent of Model
     *
     * @param  HasMany<TItem, TParent>  $items
     * @param  array<Data>  $rows
     */
    public function replace(HasMany $items, array $rows): void
    {
        $this->store($items, $rows, fn (array $values): array => []);
    }

    /**
     * @template TItem of Model
     * @template TParent of Model
     *
     * @param  HasMany<TItem, TParent>  $items
     * @param  array<Data>  $rows
     */
    public function replacePriced(HasMany $items, array $rows, bool $isVatPayer): void
    {
        $this->store($items, $rows, fn (array $values): array => $this->totals->line(
            (float) $values['quantity'],
            (float) $values['unit_price'],
            (float) $values['discount_percent'],
            (float) $values['vat_rate'],
            $isVatPayer,
        ));
    }

    /**
     * @param  iterable<InvoiceItem|QuoteItem>  $items
     * @return array{subtotal: float, vat_amount: float, total: float, rounding_amount: float, vat_breakdown: list<array{rate: float, base: float, vat: float, total: float}>|null}
     */
    public function totals(iterable $items, bool $isVatPayer, RoundingModeEnum $roundingMode = RoundingModeEnum::None): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = [
                'vat_rate' => $item->vat_rate,
                'line_base' => $item->line_base,
                'line_vat' => $item->line_vat,
                'line_total' => $item->line_total,
            ];
        }

        return $this->totals->totals($lines, $isVatPayer, $roundingMode);
    }

    /**
     * @template TItem of Model
     * @template TParent of Model
     *
     * @param  HasMany<TItem, TParent>  $items
     * @param  array<Data>  $rows
     * @param  callable(array<string, scalar|null>): array<string, float>  $extra
     */
    private function store(HasMany $items, array $rows, callable $extra): void
    {
        $items->delete();

        foreach ($rows as $position => $row) {
            /** @var array<string, scalar|null> $values */
            $values = $row->except('id', 'line_base', 'line_vat', 'line_total')->toArray();

            $items->create([
                ...$values,
                ...$extra($values),
                'tenant_id' => $items->getParent()->getAttribute('tenant_id'),
                'position' => $position,
            ]);
        }
    }
}
