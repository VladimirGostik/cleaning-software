<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    /** @var class-string<Invoice> */
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-3 months', 'now');
        $dueDate = fake()->dateTimeBetween($issueDate, '+30 days');

        return [
            'type' => InvoiceTypeEnum::OneOff,
            'status' => InvoiceStatusEnum::Draft,
            'template' => InvoiceTemplateEnum::Classic,
            'number' => null,
            'variable_symbol' => null,
            'period_from' => null,
            'period_to' => null,
            'issue_date' => $issueDate->format('Y-m-d'),
            'delivery_date' => $issueDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'issued_at' => null,
            'sent_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'is_vat_payer' => false,
            'vat_rate' => '23.00',
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'deposit' => '0.00',
            'vat_breakdown' => null,
            'rounding_amount' => '0.00',
            'constant_symbol' => null,
            'specific_symbol' => null,
            'payment_type' => PaymentTypeEnum::Transfer,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::None,
            'header_text' => null,
            'footer_text' => null,
            'customer_name' => fake()->company(),
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => fake()->city(),
            'customer_postal_code' => null,
            'customer_country' => 'SK',
            'customer_email' => null,
            'object_name' => null,
            'object_street' => null,
            'object_city' => null,
            'object_postal_code' => null,
            'supplier_name' => fake()->company(),
            'supplier_ico' => null,
            'supplier_dic' => null,
            'supplier_vat_number' => null,
            'supplier_iban' => null,
            'supplier_swift' => null,
            'supplier_address_line' => null,
            'supplier_city' => null,
            'supplier_postal_code' => null,
            'supplier_country' => 'SK',
            'supplier_contact_email' => null,
            'supplier_contact_phone' => null,
            'supplier_registration_info' => null,
            'note' => null,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'client_id' => $client->id,
            'customer_name' => $client->name,
            'customer_ico' => $client->ico,
            'customer_dic' => $client->dic,
            'customer_vat_number' => $client->vat_number,
            'customer_street' => $client->street,
            'customer_city' => $client->city,
            'customer_postal_code' => $client->postal_code,
            'customer_country' => $client->country,
        ]);
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => [
            'cleaning_object_id' => $object->id,
            'object_name' => $object->name,
            'object_street' => $object->street,
            'object_city' => $object->city,
            'object_postal_code' => $object->postal_code,
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::Issued,
            'number' => 'FA-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'variable_symbol' => date('Y').fake()->unique()->numerify('####'),
            'issued_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::Paid,
            'number' => 'FA-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'variable_symbol' => date('Y').fake()->unique()->numerify('####'),
            'issued_at' => now()->subDays(5),
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::Overdue,
            'number' => 'FA-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'variable_symbol' => date('Y').fake()->unique()->numerify('####'),
            'issued_at' => now()->subDays(20),
            'due_date' => now()->subDays(5)->format('Y-m-d'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatusEnum::Cancelled,
            'number' => 'FA-'.date('Y').'-'.fake()->unique()->numerify('####'),
            'variable_symbol' => date('Y').fake()->unique()->numerify('####'),
            'issued_at' => now()->subDays(10),
            'cancelled_at' => now(),
        ]);
    }

    public function vatPayer(): static
    {
        return $this->state(fn () => [
            'is_vat_payer' => true,
            'vat_rate' => '23.00',
            'vat_amount' => '23.00',
            'total' => '123.00',
            'vat_breakdown' => [
                ['rate' => 23.0, 'base' => 100.0, 'vat' => 23.0, 'total' => 123.0],
            ],
        ]);
    }

    public function nonVatPayer(): static
    {
        return $this->state(fn () => [
            'is_vat_payer' => false,
            'vat_amount' => '0.00',
            'vat_breakdown' => null,
        ]);
    }

    public function withDeposit(float $deposit = 50.0): static
    {
        return $this->state(fn () => [
            'deposit' => number_format($deposit, 2, '.', ''),
        ]);
    }
}
