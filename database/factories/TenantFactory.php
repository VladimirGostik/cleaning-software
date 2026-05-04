<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' s.r.o.',
            'ico' => (string) fake()->unique()->numberBetween(10000000, 99999999),
            'dic' => '20' . fake()->numerify('########'),
            'vat_number' => 'SK' . fake()->numerify('##########'),
            'is_vat_payer' => true,
            'vat_rate' => 23,
            'iban' => 'SK' . fake()->numerify('##########################'),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => 'SK',
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'subscription_plan' => 'basic',
            'is_active' => true,
        ];
    }
}
