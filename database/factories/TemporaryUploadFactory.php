<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TemporaryUpload;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemporaryUpload>
 */
final class TemporaryUploadFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => fake()->uuid(),
            'user_id' => null,
            'tenant_id' => Tenant::factory(),
        ];
    }
}
