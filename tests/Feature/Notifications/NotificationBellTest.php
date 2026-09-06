<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_bell_counts_unread_and_caps_recent_at_five(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->count(4)->create();
        Notification::factory()->for($admin, 'notifiable')->for($tenant)->read()->count(3)->create();

        $response = $this->getJson(route('notifications.bell'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 4);
        $this->assertCount(5, (array) $response->json('recent'));
    }

    public function test_web_bell_is_tenant_scoped(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $otherTenant = Tenant::factory()->create();

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();
        Notification::factory()->for($admin, 'notifiable')->for($otherTenant)->create();

        $response = $this->getJson(route('notifications.bell'));

        $response->assertJsonPath('unread_count', 1);
    }

    public function test_web_bell_forbidden_without_view_notifications(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->getJson(route('notifications.bell'))->assertForbidden();
    }

    public function test_api_bell_returns_unread_count_and_recent(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        TenantMembership::query()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        RoleTemplatesSeeder::seedForTenant($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::inTenant($tenant->id)->where('name', 'Vedúca')->firstOrFail();
        $user->assignRole($role);

        Notification::factory()->for($user, 'notifiable')->for($tenant)->create();

        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $tenant->id])->getJson('/api/notifications/bell');

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
    }

    public function test_api_bell_requires_authentication(): void
    {
        $this->getJson('/api/notifications/bell')->assertUnauthorized();
    }

    public function test_api_bell_forbidden_with_header_of_tenant_not_an_active_member_of(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create();
        TenantMembership::query()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Tenant-Id' => $otherTenant->id])->getJson('/api/notifications/bell');

        $response->assertForbidden();
    }
}
