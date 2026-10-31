<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class TenantPermissionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_list_without_active_membership(): void
    {
        $this->assertSame([], app(TenantPermissionSnapshot::class)->for(User::factory()->create()));
    }

    public function test_resolves_flags_per_tenant_and_restores_team_id(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->forOwner($user)->create(['name' => 'A']);
        $tenantB = Tenant::factory()->create(['name' => 'B']);
        $this->actingAsTenantUser('Účtovníčka', $tenantA, $user);
        $this->actingAsTenantUser('Vedúca', $tenantB, $user);
        $this->bindTenant($tenantA);

        $snapshot = collect(app(TenantPermissionSnapshot::class)->for($user))->keyBy(fn ($v) => $v->tenant->id);

        $a = $snapshot->get($tenantA->id);
        $b = $snapshot->get($tenantB->id);
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertTrue($a->expenses);
        $this->assertTrue($a->manageExpenses);
        $this->assertTrue($a->tasks);
        $this->assertFalse($a->tasksAll);
        $this->assertFalse($a->mobileAccess);
        $this->assertTrue($b->mobileAccess);
        $this->assertTrue($b->tasksAll);
        $this->assertFalse($b->expenses);
        $this->assertContains(PermissionEnum::ViewExpenses, $a->permissions);
        $this->assertNotContains(PermissionEnum::ViewExpenses, $b->permissions);
        $this->assertSame($tenantA->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_ignores_permission_rows_that_are_not_enum_cases(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        Permission::create(['name' => 'legacy stray permission', 'guard_name' => 'web']);
        $user->givePermissionTo('legacy stray permission');

        $snapshot = app(TenantPermissionSnapshot::class)->for($user);

        $this->assertCount(1, $snapshot);
        $this->assertContains(PermissionEnum::AccessMobileApp, $snapshot[0]->permissions);
        $this->assertTrue(app(TenantPermissionSnapshot::class)->hasMobileAccess($user));
    }
}
