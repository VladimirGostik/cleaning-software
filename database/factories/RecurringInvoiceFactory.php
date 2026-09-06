<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringInvoice>
 */
final class RecurringInvoiceFactory extends Factory
{
    /** @var class-string<RecurringInvoice> */
    protected $model = RecurringInvoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' - Monthly cleaning',
            'type' => InvoiceTypeEnum::Monthly,
            'template' => null,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'day_of_month' => fake()->numberBetween(1, 28),
            'status' => RecurringInvoiceStatusEnum::Active,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'occurrences_limit' => null,
            'occurrences_generated' => 0,
            'next_run_at' => now()->addMonth()->toDateString(),
            'last_generated_at' => null,
            'customer_name' => fake()->company(),
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => fake()->city(),
            'customer_postal_code' => null,
            'customer_country' => 'SK',
            'customer_email' => null,
            'period_from' => null,
            'period_to' => null,
            'due_days' => 14,
            'deposit' => '0.00',
            'note' => null,
            'constant_symbol' => null,
            'payment_type' => PaymentTypeEnum::Transfer,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::None,
            'header_text' => null,
            'footer_text' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => RecurringInvoiceStatusEnum::Active,
            'next_run_at' => now()->addMonth()->toDateString(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => RecurringInvoiceStatusEnum::Paused,
            'next_run_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => RecurringInvoiceStatusEnum::Completed,
            'next_run_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => RecurringInvoiceStatusEnum::Cancelled,
            'next_run_at' => null,
        ]);
    }

    public function withEndDate(): static
    {
        return $this->state(fn () => [
            'end_date' => now()->addYear()->toDateString(),
        ]);
    }

    public function withLimit(int $limit = 12): static
    {
        return $this->state(fn () => [
            'occurrences_limit' => $limit,
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn () => [
            'status' => RecurringInvoiceStatusEnum::Active,
            'next_run_at' => now()->toDateString(),
        ]);
    }

    public function withDeposit(float $deposit = 50.0): static
    {
        return $this->state(fn () => [
            'deposit' => number_format($deposit, 2, '.', ''),
        ]);
    }
}
