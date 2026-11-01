<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ActionChannelEnum;
use App\Mcp\Servers\CleanMasterServer;
use App\Mcp\Tools\CleanMasterTool;
use App\Mcp\Tools\ConfirmActionTool;
use App\Mcp\Tools\DeleteGlobalTaskTool;
use App\Services\Ai\AssistantTurn;

final class AssistantToolbox
{
    /** Never offered to the assistant: confirming is a UI tap, and a direct irreversible delete has no confirmation step. */
    public const array EXCLUDED = [
        ConfirmActionTool::class,
        DeleteGlobalTaskTool::class,
    ];

    /** @return list<class-string<CleanMasterTool>> */
    public static function toolClasses(): array
    {
        return array_values(array_diff(CleanMasterServer::TOOLS, self::EXCLUDED));
    }

    /** @return list<McpToolAdapter> */
    public function make(AssistantTurn $turn): array
    {
        $adapters = [];

        foreach (self::toolClasses() as $class) {
            /** @var CleanMasterTool $tool */
            $tool = app($class);
            $adapters[] = new McpToolAdapter($tool->asChannel(ActionChannelEnum::Assistant), $turn);
        }

        return $adapters;
    }
}
