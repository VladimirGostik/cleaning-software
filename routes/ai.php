<?php

declare(strict_types=1);

use App\Mcp\Servers\CleanMasterServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', CleanMasterServer::class)->middleware(['auth:sanctum', 'locale.api', 'user.active', 'ability:mcp', 'throttle:mcp']);

Mcp::local('cleanmaster', CleanMasterServer::class);
