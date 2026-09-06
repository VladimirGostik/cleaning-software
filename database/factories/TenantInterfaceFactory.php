<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantInterface>
 */
final class TenantInterfaceFactory extends Factory
{
    /** @var class-string<TenantInterface> */
    protected $model = TenantInterface::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'color' => null,
        ];
    }
}
