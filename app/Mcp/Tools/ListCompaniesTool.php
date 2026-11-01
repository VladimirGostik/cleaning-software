<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_companies')]
#[Description('Lists the companies the user belongs to with the permissions held in each. Call this first: every other tool needs a company_id from here.')]
#[IsReadOnly]
final class ListCompaniesTool extends CleanMasterTool
{
    public function handle(Request $request, TenantPermissionSnapshot $snapshot): Response
    {
        return $this->guard($request, null, fn ($user): Response => $this->json([
            'companies' => array_map(fn (TenantVisibility $visibility): array => [
                'company_id' => $visibility->tenant->id,
                'name' => $visibility->tenant->name,
                'permissions' => array_map(fn ($permission): string => $permission->value, $visibility->permissions),
            ], $snapshot->forChannel($user, $this->channel())),
        ]));
    }
}
