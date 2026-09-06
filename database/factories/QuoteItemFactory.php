<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskFrequencyEnum;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
final class QuoteItemFactory extends Factory
{
    /** @var class-string<QuoteItem> */
    protected $model = QuoteItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $lineBase = round($quantity * $unitPrice, 2);

        return [
            'description' => fake()->sentence(3),
            'frequency' => null,
            'note' => null,
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

    public function withNote(string $note = 'Includes window cleaning'): static
    {
        return $this->state(fn () => ['note' => $note]);
    }

    public function withFrequency(TaskFrequencyEnum $frequency = TaskFrequencyEnum::Weekly1x): static
    {
        return $this->state(fn () => ['frequency' => $frequency]);
    }

    public function withVat(float $rate = 23.0): static
    {
        return $this->state(function (array $attributes) use ($rate): array {
            $base = self::toFloat($attributes['line_base'] ?? null);
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
            $quantity = self::toFloat($attributes['quantity'] ?? null);
            $unitPrice = self::toFloat($attributes['unit_price'] ?? null);
            $base = round($quantity * $unitPrice * (1 - $percent / 100), 2);
            $rate = self::toFloat($attributes['vat_rate'] ?? null);
            $vat = round($base * $rate / 100, 2);

            return [
                'discount_percent' => $percent,
                'line_base' => $base,
                'line_vat' => $vat,
                'line_total' => round($base + $vat, 2),
            ];
        });
    }

    private static function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
