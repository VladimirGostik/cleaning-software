<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    /** @var class-string<Notification> */
    protected $model = Notification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'type' => NotificationTypeEnum::InvoiceOverdue->value,
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'data' => [
                'type' => NotificationTypeEnum::InvoiceOverdue->value,
                'title' => fake()->sentence(3),
                'body' => fake()->sentence(8),
                'url' => null,
                'meta' => [],
            ],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function ofType(NotificationTypeEnum $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type->value,
            'data' => array_merge(is_array($attributes['data'] ?? null) ? $attributes['data'] : [], ['type' => $type->value]),
        ]);
    }
}
