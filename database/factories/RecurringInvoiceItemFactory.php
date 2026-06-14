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
    protected $model = RecurringInvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(4),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit' => 'hod',
            'unit_price' => fake()->randomFloat(2, 10, 200),
            'position' => 0,
        ];
    }
}
