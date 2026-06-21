<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
final class QuoteItemFactory extends Factory
{
    protected $model = QuoteItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = (float) fake()->randomFloat(2, 10, 500);
        $quantity = (float) fake()->randomFloat(2, 1, 10);
        $lineBase = round($unitPrice * $quantity, 2);

        return [
            'tenant_id' => null, // set via relationship
            'quote_id' => Quote::factory(),
            'name' => fake()->words(3, true),
            'description' => null,
            'frequency' => null,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit' => 'ks',
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'discount_percent' => '0.00',
            'vat_rate' => '0.00',
            'line_base' => number_format($lineBase, 2, '.', ''),
            'line_vat' => '0.00',
            'line_total' => number_format($lineBase, 2, '.', ''),
            'position' => 0,
        ];
    }
}
