<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskSourceEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    /** @var class-string<Task> */
    protected $model = Task::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by_user_id' => User::factory(),
            'assigned_membership_id' => null,
            'title' => fake()->sentence(4),
            'description' => null,
            'status' => TaskStatusEnum::Open,
            'priority' => TaskPriorityEnum::Normal,
            'due_at' => null,
            'remind_at' => null,
            'reminded_at' => null,
            'completed_at' => null,
            'source' => TaskSourceEnum::Manual,
            'taskable_type' => null,
            'taskable_id' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    public function global(): static
    {
        return $this->state(fn () => ['tenant_id' => null, 'assigned_membership_id' => null]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by_user_id' => $user->id]);
    }

    public function assignedTo(TenantMembership $membership): static
    {
        return $this->state(fn () => ['tenant_id' => $membership->tenant_id, 'assigned_membership_id' => $membership->id]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => TaskStatusEnum::Done, 'completed_at' => now()]);
    }

    public function dueReminder(): static
    {
        return $this->state(fn () => ['remind_at' => now()->subMinute(), 'reminded_at' => null]);
    }
}
