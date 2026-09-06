<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatusEnum;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantInvitation>
 */
final class TenantInvitationFactory extends Factory
{
    /** @var class-string<TenantInvitation> */
    protected $model = TenantInvitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by_user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_name' => fake()->randomElement(['Vedúca', 'Interná upratovačka', 'Sekretárka', 'Účtovníčka', 'Zákazník']),
            'token' => Str::random(64),
            'status' => InvitationStatusEnum::Pending->value,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => InvitationStatusEnum::Accepted->value,
            'accepted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => InvitationStatusEnum::Expired->value,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state([
            'status' => InvitationStatusEnum::Revoked->value,
        ]);
    }
}
