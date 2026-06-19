<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
final class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $lineBase = round($quantity * $unitPrice, 2);

        return [
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['hod', 'ks', 'm²', null]),
            'unit_price' => $unitPrice,
            'discount_percent' => 0,
            'vat_rate' => 0,
            'line_base' => $lineBase,
            'line_vat' => 0,
            'line_total' => $lineBase,
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function withVat(float $rate = 23.0): static
    {
        return $this->state(function (array $attributes) use ($rate): array {
            $base = (float) $attributes['line_base'];
            $vat = round($base * $rate / 100, 2);

            return [
                'vat_rate' => $rate,
                'line_vat' => $vat,
                'line_total' => round($base + $vat, 2),
            ];
        });
    }

    public function withDiscount(float $percent = 10.0): static
    {
        return $this->state(function (array $attributes) use ($percent): array {
            $quantity = (float) $attributes['quantity'];
            $unitPrice = (float) $attributes['unit_price'];
            $base = round($quantity * $unitPrice * (1 - $percent / 100), 2);
            $rate = (float) $attributes['vat_rate'];
            $vat = round($base * $rate / 100, 2);

            return [
                'discount_percent' => $percent,
                'line_base' => $base,
                'line_vat' => $vat,
                'line_total' => round($base + $vat, 2),
            ];
        });
    }
}
