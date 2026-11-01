<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Mcp\Servers\CleanMasterServer;
use App\Mcp\Tools\AssignTaskTool;
use App\Mcp\Tools\CleanMasterTool;
use App\Mcp\Tools\CompanyOverviewTool;
use App\Mcp\Tools\CompleteTaskTool;
use App\Mcp\Tools\ConfirmActionTool;
use App\Mcp\Tools\DeleteGlobalTaskTool;
use App\Mcp\Tools\ListCompaniesTool;
use App\Mcp\Tools\UpdateDocumentTemplateTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Services\Ai\AssistantTurn;
use App\Services\Ai\Guard\PromptGuard;
use Laravel\Ai\Contracts\Tool;

final class AssistantToolbox
{
    /** Never offered to the assistant: confirming is a UI tap, and a direct irreversible delete has no confirmation step. */
    public const array EXCLUDED = [
        ConfirmActionTool::class,
        DeleteGlobalTaskTool::class,
        UpdateDocumentTemplateTool::class,
    ];

    /** A run is pinned to one company and cannot change existing tasks, including the one it works on. */
    public const array EXCLUDED_FROM_TASK_RUN = [
        ListCompaniesTool::class,
        CompanyOverviewTool::class,
        CompleteTaskTool::class,
        UpdateTaskTool::class,
        AssignTaskTool::class,
    ];

    /** @return list<class-string<CleanMasterTool>> */
    public static function toolClasses(): array
    {
        return array_values(array_diff(CleanMasterServer::TOOLS, self::EXCLUDED));
    }

    /** @return list<class-string<CleanMasterTool>> */
    public static function taskRunToolClasses(): array
    {
        return array_values(array_diff(self::toolClasses(), self::EXCLUDED_FROM_TASK_RUN));
    }

    /** @return list<McpToolAdapter> */
    public function make(AssistantTurn $turn): array
    {
        return $this->adapt(self::toolClasses(), $turn);
    }

    /** @return list<Tool> */
    public function forTaskRun(AssistantTurn $turn): array
    {
        return [...$this->adapt(self::taskRunToolClasses(), $turn), new ReportTaskOutcomeTool($turn)];
    }

    /**
     * @param  list<class-string<CleanMasterTool>>  $classes
     * @return list<McpToolAdapter>
     */
    private function adapt(array $classes, AssistantTurn $turn): array
    {
        $adapters = [];

        foreach ($classes as $class) {
            /** @var CleanMasterTool $tool */
            $tool = app($class);
            $adapters[] = new McpToolAdapter($tool->asChannel($turn->channel), $turn, app(PromptGuard::class));
        }

        return $adapters;
    }
}
