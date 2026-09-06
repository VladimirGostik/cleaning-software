<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_for_user_with_no_active_membership_is_empty(): void
    {
        $user = User::factory()->create();

        $overview = app(DashboardService::class)->overviewFor($user);

        $this->assertSame([], $overview->companies);
        $this->assertSame([], $overview->alerts);
        $this->assertCount(5, $overview->alert_counts);
        foreach ($overview->alert_counts as $count) {
            $this->assertSame(0, $count->count);
        }
    }

    public function test_overview_for_restores_previous_permissions_team_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        app(DashboardService::class)->overviewFor($user);

        $this->assertSame($previous, $registrar->getPermissionsTeamId());
    }
}
