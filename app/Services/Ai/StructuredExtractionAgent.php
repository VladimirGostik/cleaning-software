<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[MaxTokens(4096)]
final class StructuredExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @param  Closure(JsonSchema): array<string, Type>  $schemaBuilder */
    public function __construct(
        private readonly string $systemPrompt,
        private readonly Closure $schemaBuilder,
    ) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ($this->schemaBuilder)($schema);
    }
}
