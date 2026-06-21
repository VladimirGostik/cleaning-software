<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
final class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'status' => QuoteStatusEnum::Draft,
            'number' => 'CP' . date('Y') . str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'subject' => fake()->sentence(4),
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
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

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Sent,
            'sent_at' => now()->subHour(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Accepted,
            'sent_at' => now()->subDays(2),
            'accepted_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Rejected,
            'sent_at' => now()->subDays(2),
            'rejected_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatusEnum::Expired,
            'valid_until' => now()->subDay()->toDateString(),
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

    public function forClient(Client $client): static
    {
        return $this->state(fn () => [
            'client_id' => $client->id,
            'tenant_id' => $client->tenant_id,
        ]);
    }

    public function forObject(CleaningObject $object): static
    {
        return $this->state(fn () => [
            'cleaning_object_id' => $object->id,
            'client_id' => $object->client_id,
            'tenant_id' => $object->tenant_id,
        ]);
    }
}
