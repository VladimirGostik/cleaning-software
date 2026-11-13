<?php

declare(strict_types=1);

namespace App\Services\Ai;

final readonly class AiClientOptions
{
    public string $provider;

    public string $model;

    public int $timeout;

    public int $assistantTimeout;

    public string $assistantEffort;

    public string $taskRunEffort;

    public function __construct()
    {
        $this->provider = config()->string('ai.default', 'anthropic');
        $this->model = config()->string('ai.model', 'claude-sonnet-5-5');
        $this->timeout = config()->integer('ai.timeout', 30);
        $this->assistantTimeout = config()->integer('ai.assistant_timeout', 60);
        $this->assistantEffort = config()->string('ai.assistant.effort', 'medium');
        $this->taskRunEffort = config()->string('ai.task_run.effort', 'medium');
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.providers.'.$this->provider.'.key'));
    }

    /** @return array<string, string>|null */
    public function thinkingOff(): ?array
    {
        /** @var array<string, array<string, string>> $off */
        $off = config()->array('ai.thinking_off', []);

        return $off[$this->model] ?? null;
    }
}
