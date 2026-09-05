<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

final class Permission extends SpatiePermission
{
    use HasUuids;
}
