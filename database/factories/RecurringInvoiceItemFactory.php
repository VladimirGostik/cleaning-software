<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecurringInvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringInvoiceItem>
 */
final class RecurringInvoiceItemFactory extends Factory
{
    /** @var class-string<RecurringInvoiceItem> */
    protected $model = RecurringInvoiceItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(4),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit' => 'hod',
            'unit_price' => fake()->randomFloat(2, 10, 200),
            'discount_percent' => 0,
            'vat_rate' => 0,
            'position' => 0,
        ];
    }

    public function withVat(float $rate = 23.0): static
    {
        return $this->state(fn () => ['vat_rate' => $rate]);
    }

    public function withDiscount(float $percent = 10.0): static
    {
        return $this->state(fn () => ['discount_percent' => $percent]);
    }
}
