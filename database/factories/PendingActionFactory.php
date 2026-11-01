<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActionChannelEnum;
use App\Enums\PendingActionEnum;
use App\Enums\PendingActionStatusEnum;
use App\Models\PendingAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PendingAction>
 */
final class PendingActionFactory extends Factory
{
    /** @var class-string<PendingAction> */
    protected $model = PendingAction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'action' => PendingActionEnum::DeleteTask,
            'subject_type' => PendingActionEnum::DeleteTask->subjectClass(),
            'subject_id' => (string) Str::uuid7(),
            'payload' => [],
            'payload_hash' => hash('sha256', '[]'),
            'subject_hash' => hash('sha256', 'subject'),
            'token_hash' => hash('sha256', Str::random(40)),
            'preview' => 'Preview',
            'subject_label' => 'Subject',
            'channel' => ActionChannelEnum::Mcp,
            'status' => PendingActionStatusEnum::Pending,
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }
}
