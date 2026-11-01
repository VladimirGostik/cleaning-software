<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[MaxSteps(6)]
#[MaxTokens(2048)]
final class AssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Tool>  $tools
     * @param  list<Message>  $history
     */
    public function __construct(
        private readonly string $systemPrompt,
        private readonly array $tools,
        private readonly array $history,
    ) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return $this->tools;
    }

    /** @return list<Message> */
    public function messages(): iterable
    {
        return $this->history;
    }

    public function maxSteps(): int
    {
        return config()->integer('ai.assistant.max_steps', 6);
    }
}
