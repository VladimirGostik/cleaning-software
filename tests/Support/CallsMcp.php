<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

trait CallsMcp
{
    private int $rpcId = 1;

    protected function mcpToken(User $user, string $name = 'mcp'): string
    {
        return $user->createToken($name, ['mcp'])->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<Response>
     */
    protected function rpc(string $token, string $method, array $params = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => $this->rpcId++,
            'method' => $method,
            'params' => $params,
        ], ['Accept' => 'application/json, text/event-stream']);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function callTool(string $token, string $tool, array $arguments = []): McpResult
    {
        $response = $this->rpc($token, 'tools/call', ['name' => $tool, 'arguments' => $arguments])->assertOk();

        /** @var array{result: array{isError?: bool, content: list<array{text: string}>}} $body */
        $body = $response->json();
        $text = $body['result']['content'][0]['text'] ?? '';

        $decoded = json_decode($text, true);

        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        return new McpResult((bool) ($body['result']['isError'] ?? false), $text, $data);
    }
}
