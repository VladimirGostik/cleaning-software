<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

return [
    /*
     * Map of FQCN => [route_name, route_param_name].
     * Used by MediaUrlResolver to build owner edit/show URL for a media record.
     * Add new entry whenever a new model becomes a media owner.
     */
    'models' => [
        User::class => ['name' => 'users.edit', 'param' => 'user'],
        // settings.invoicing takes no route parameter — omit 'param', resolver links to the plain route.
        Tenant::class => ['name' => 'settings.invoicing'],
    ],
];
