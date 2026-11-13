<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\TextResponse;

final readonly class AiUsageLog
{
    public function __construct(private AiClientOptions $options) {}

    /** @param  array<string, string>|null  $thinking */
    public function record(string $event, int $startedAt, string $effort, ?array $thinking, ?TextResponse $response): void
    {
        $context = [
            'model' => $this->options->model,
            'thinking' => $thinking['type'] ?? 'default',
            'effort' => $effort,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'completed' => $response !== null,
        ];

        if ($response !== null) {
            $context += [
                'steps' => $response->steps->count(),
                'input_tokens' => $response->usage->promptTokens,
                'output_tokens' => $response->usage->completionTokens,
                'cache_read_input_tokens' => $response->usage->cacheReadInputTokens,
                'cache_creation_input_tokens' => $response->usage->cacheWriteInputTokens,
            ];
        }

        Log::info($event, $context);
    }
}
