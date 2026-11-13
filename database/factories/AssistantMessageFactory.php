<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssistantRoleEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessage>
 */
final class AssistantMessageFactory extends Factory
{
    /** @var class-string<AssistantMessage> */
    protected $model = AssistantMessage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assistant_conversation_id' => AssistantConversation::factory(),
            'role' => AssistantRoleEnum::User,
            'content' => fake()->sentence(),
        ];
    }

    public function assistant(): self
    {
        return $this->state(['role' => AssistantRoleEnum::Assistant]);
    }
}
