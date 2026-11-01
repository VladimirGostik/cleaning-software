<?php

declare(strict_types=1);

namespace App\Services\Ai;

final readonly class AiClientOptions
{
    public string $provider;

    public string $model;

    public int $timeout;

    public int $assistantTimeout;

    public function __construct()
    {
        $this->provider = config()->string('ai.default', 'anthropic');
        $this->model = config()->string('ai.model', 'claude-sonnet-5-5');
        $this->timeout = config()->integer('ai.timeout', 30);
        $this->assistantTimeout = config()->integer('ai.assistant_timeout', 60);
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.providers.'.$this->provider.'.key'));
    }
}
