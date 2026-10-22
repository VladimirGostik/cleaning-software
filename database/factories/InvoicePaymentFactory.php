<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
final class InvoicePaymentFactory extends Factory
{
    /** @var class-string<InvoicePayment> */
    protected $model = InvoicePayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => '10.00',
            'paid_on' => now()->toDateString(),
            'payment_type' => PaymentTypeEnum::Transfer,
            'source' => PaymentSourceEnum::Manual,
        ];
    }
}
