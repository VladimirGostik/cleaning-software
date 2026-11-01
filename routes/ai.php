<?php

declare(strict_types=1);

use App\Mcp\Servers\CleanMasterServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', CleanMasterServer::class)->middleware(['auth:sanctum', 'user.active', 'ability:mcp', 'locale.api', 'throttle:mcp']);

Mcp::local('cleanmaster', CleanMasterServer::class);
