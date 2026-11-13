<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[MaxSteps(6)]
#[MaxTokens(2048)]
final class AssistantAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    // Owner uses the assistant in bursts with 5 to 60 minute gaps, so the 5 minute default would expire between turns.
    private const string RULES_CACHE_TTL = '1h';

    /**
     * @param  list<Tool>  $tools
     * @param  list<Message>  $history
     * @param  array<string, string>|null  $thinking
     */
    public function __construct(
        private readonly string $rules,
        private readonly string $context,
        private readonly array $tools,
        private readonly array $history,
        private readonly string $effort,
        private readonly ?array $thinking,
    ) {}

    public function instructions(): string
    {
        return $this->rules."\n".$this->context;
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== Lab::Anthropic && $provider !== Lab::Anthropic->value) {
            return [];
        }

        $system = [['type' => 'text', 'text' => $this->rules, 'cache_control' => ['type' => 'ephemeral', 'ttl' => self::RULES_CACHE_TTL]]];

        if ($this->context !== '') {
            $system[] = ['type' => 'text', 'text' => $this->context];
        }

        $options = [
            'system' => $system,
            'cache_control' => ['type' => 'ephemeral'],
            'output_config' => ['effort' => $this->effort],
        ];

        if ($this->thinking !== null) {
            $options['thinking'] = $this->thinking;
        }

        return $options;
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
