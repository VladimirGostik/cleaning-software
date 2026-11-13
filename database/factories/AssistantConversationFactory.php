<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantConversation>
 */
final class AssistantConversationFactory extends Factory
{
    /** @var class-string<AssistantConversation> */
    protected $model = AssistantConversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'last_message_at' => now(),
        ];
    }
}
