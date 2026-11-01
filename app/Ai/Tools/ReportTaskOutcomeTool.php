<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\TaskRunOutcomeEnum;
use App\Services\Ai\AssistantTurn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/** Run-only: writes nothing, the server turns the recorded outcome into the task comment after the run. */
final readonly class ReportTaskOutcomeTool implements Tool
{
    public function __construct(private AssistantTurn $turn) {}

    public function name(): string
    {
        return 'report_task_outcome';
    }

    public function description(): string
    {
        return 'Call exactly once at the end. outcome "done": everything the task asks for was done. "question": you need an answer from the person; put the question in message. "cannot": you could not do it; say why in message. message is shown to the person as a comment, in the language of the task, plain text.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'outcome' => $schema->string()->enum(array_map(static fn (TaskRunOutcomeEnum $case): string => $case->value, TaskRunOutcomeEnum::cases()))->required(),
            'message' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $outcome = TaskRunOutcomeEnum::tryFrom((string) $request->string('outcome'));
        $message = trim((string) $request->string('message'));

        if ($outcome === null || $message === '') {
            return 'Error: outcome and message are required.';
        }

        return $this->turn->reportOutcome($outcome, $message) ? 'Recorded. Stop now.' : 'Error: an outcome was already reported.';
    }
}
