<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
final class QuoteFactory extends Factory
{
    /** @var class-string<Quote> */
    protected $model = Quote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'status' => QuoteStatusEnum::Draft,
            'kind' => QuoteKindEnum::Itemized,
            'number' => null,
            'subject' => fake()->sentence(3),
            'customer_name' => fake()->company(),
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => fake()->city(),
            'customer_postal_code' => null,
            'issue_date' => $issueDate->format('Y-m-d'),
            'valid_until' => (clone $issueDate)->modify('+30 days')->format('Y-m-d'),
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'is_vat_payer' => false,
            'vat_rate' => null,
            'currency' => CurrencyEnum::EUR,
            'subtotal' => '100.00',
            'vat_amount' => '0.00',
            'total' => '100.00',
            'vat_breakdown' => null,
            'note' => null,
        ];
    }

    public function numbered(): static
    {
        return $this->state(fn () => [
            'number' => 'CP-'.date('Y').'-'.fake()->unique()->numerify('####'),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Sent,
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Accepted,
            'sent_at' => now()->subDay(),
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Rejected,
            'sent_at' => now()->subDay(),
            'rejected_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Expired,
            'issue_date' => now()->subMonths(2)->format('Y-m-d'),
            'valid_until' => now()->subDays(5)->format('Y-m-d'),
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

    public function withoutClient(): static
    {
        return $this->state(fn () => [
            'client_id' => null,
            'cleaning_object_id' => null,
        ]);
    }

    public function document(): static
    {
        return $this->state(fn () => [
            'kind' => QuoteKindEnum::Document,
            'subtotal' => '0.00',
            'vat_amount' => '0.00',
            'total' => '0.00',
        ]);
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'client_id' => $client->id,
            'customer_name' => null,
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
        ]);
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => [
            'cleaning_object_id' => $object->id,
        ]);
    }
}
