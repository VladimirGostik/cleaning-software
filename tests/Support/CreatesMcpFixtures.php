<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

trait CreatesMcpFixtures
{
    use CallsMcp;
    use CreatesTaskFixtures;

    /** Adds ad-hoc permissions directly to the user inside one firm. */
    protected function grant(User $user, Tenant $tenant, string ...$permissions): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->givePermissionTo($permissions);
    }

    /** @return Collection<int, Activity> */
    protected function aiActivities(): Collection
    {
        return Activity::query()->where('log_name', 'ai')->orderBy('created_at')->get();
    }
}
