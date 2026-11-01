<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait FakesAnthropicProvider
{
    /**
     * @param  list<array{name: string, input: array<string, mixed>}>  $toolUses
     * @return array<string, mixed>
     */
    protected function toolTurn(array $toolUses): array
    {
        return [
            'id' => 'msg_tool', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => array_map(static fn (array $use, int $i): array => ['type' => 'tool_use', 'id' => 'toolu_'.$i, 'name' => $use['name'], 'input' => (object) $use['input']], $toolUses, array_keys($toolUses)),
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    /** @return array<string, mixed> */
    protected function textTurn(string $text): array
    {
        return [
            'id' => 'msg_text', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    /** @param  list<array<string, mixed>>  $responses */
    protected function providerSays(array $responses, bool $thenServerError = false): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        $sequence = Http::sequence();

        foreach ($responses as $response) {
            $sequence->push($response);
        }

        if ($thenServerError) {
            $sequence->push(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']], 500);
        }

        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    /** @return list<Request> */
    protected function sentRequests(): array
    {
        return array_values(array_map(static fn (array $pair): Request => $pair[0], Http::recorded()->all()));
    }

    /** @return list<string> */
    protected function toolResults(Request $request): array
    {
        $results = [];

        foreach ((array) data_get($request->data(), 'messages', []) as $message) {
            foreach ((array) (is_array($message) ? ($message['content'] ?? []) : []) as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'tool_result') {
                    $results[] = is_string($block['content'] ?? null) ? $block['content'] : (string) json_encode($block['content'] ?? '');
                }
            }
        }

        return $results;
    }
}
