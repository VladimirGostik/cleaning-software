<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ClientType::Corporate,
            'name' => fake()->company(),
            'ico' => (string) fake()->numberBetween(10000000, 99999999),
            'dic' => '20' . fake()->numerify('########'),
            'vat_number' => 'SK' . fake()->numerify('##########'),
            'is_vat_payer' => true,
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'SK',
            'note' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'type' => ClientType::Private,
            'name' => fake()->name(),
            'ico' => null,
            'dic' => null,
            'vat_number' => null,
            'is_vat_payer' => false,
        ]);
    }

    public function withContacts(int $count = 2): static
    {
        return $this->afterCreating(function (Client $client) use ($count): void {
            ClientContact::factory()->count($count)->for($client)->create([
                'tenant_id' => $client->tenant_id,
            ]);
            $client->contacts()->first()?->update(['is_primary' => true]);
        });
    }
}
