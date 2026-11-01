<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Mcp\Tools\CleanMasterTool;
use App\Services\Ai\AssistantTurn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Laravel\Mcp\Request as McpRequest;
use Throwable;

final readonly class McpToolAdapter implements Tool
{
    public function __construct(private CleanMasterTool $tool, private AssistantTurn $turn) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return array_filter($this->tool->schema($schema), static fn (mixed $property): bool => $property instanceof Type);
    }

    public function handle(Request $request): string
    {
        if ($this->turn->expired()) {
            return 'Error: '.__('app.assistant_time_budget');
        }

        try {
            $response = $this->tool->invoke(new McpRequest($request->all()));
        } catch (Throwable $exception) {
            Log::warning('assistant.tool_failed', ['tool' => $this->tool->name(), 'exception' => $exception::class]);

            return 'Error: '.__('app.tool_failed');
        }

        $text = (string) $response->content();

        if ($response->isError()) {
            return 'Error: '.$text;
        }

        $this->turn->observe($this->tool->name(), $text);

        $max = config()->integer('ai.assistant.max_tool_output_chars', 20000);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).' ...['.__('app.assistant_output_truncated').']' : $text;
    }
}
