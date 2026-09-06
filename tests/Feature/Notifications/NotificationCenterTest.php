<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_own_rows_of_active_tenant_ordered_desc(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        $older = Notification::factory()->for($admin, 'notifiable')->for($tenant)->create(['created_at' => now()->subDay()]);
        $newer = Notification::factory()->for($admin, 'notifiable')->for($tenant)->create(['created_at' => now()]);

        $response = $this->get(route('notifications.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 2)
            ->where('notifications.data.0.id', $newer->id)
            ->where('notifications.data.1.id', $older->id)
            ->where('unreadCount', 2));
    }

    public function test_filter_by_type_narrows_results(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->ofType(NotificationTypeEnum::InvoiceOverdue)->create();
        Notification::factory()->for($admin, 'notifiable')->for($tenant)->ofType(NotificationTypeEnum::QuoteSent)->create();

        $response = $this->get(route('notifications.index', ['filter' => ['type' => 'invoice.overdue']]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
    }

    public function test_filter_unread_only(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();
        Notification::factory()->for($admin, 'notifiable')->for($tenant)->read()->create();

        $unread = $this->get(route('notifications.index', ['filter' => ['read' => '0']]));
        $read = $this->get(route('notifications.index', ['filter' => ['read' => '1']]));

        $unread->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
        $read->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
    }

    public function test_invalid_type_filter_is_ignored(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();

        $response = $this->get(route('notifications.index', ['filter' => ['type' => 'not-a-real-type']]));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
    }

    public function test_cleaner_without_permission_is_forbidden(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('notifications.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_rows_of_same_user_in_another_tenant_are_excluded(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $otherTenant = Tenant::factory()->create();

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();
        Notification::factory()->for($admin, 'notifiable')->for($otherTenant)->create();

        $response = $this->get(route('notifications.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
    }

    public function test_rows_of_another_user_in_same_tenant_are_excluded(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $otherUser = User::factory()->create();

        Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();
        Notification::factory()->for($otherUser, 'notifiable')->for($tenant)->create();

        $response = $this->get(route('notifications.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 1));
    }

    public function test_mark_read_own_row_in_active_tenant(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $notification = Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();

        $response = $this->post(route('notifications.read', $notification->id));

        $response->assertRedirect();
        $response->assertSessionHas('success', __('app.notification_marked_read'));
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_read_already_read_is_idempotent(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $notification = Notification::factory()->for($admin, 'notifiable')->for($tenant)->read()->create();
        $readAt = $notification->read_at;

        $this->post(route('notifications.read', $notification->id));

        $notification->refresh();
        $this->assertSame($readAt?->toIso8601String(), $notification->read_at?->toIso8601String());
    }

    public function test_mark_read_another_users_row_is_forbidden(): void
    {
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->for($otherUser, 'notifiable')->for($tenant)->create();

        $this->post(route('notifications.read', $notification->id))->assertForbidden();
    }

    public function test_mark_read_own_row_in_another_tenant_is_forbidden(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $otherTenant = Tenant::factory()->create();
        $notification = Notification::factory()->for($admin, 'notifiable')->for($otherTenant)->create();

        $this->post(route('notifications.read', $notification->id))->assertForbidden();
    }

    public function test_mark_read_cleaner_own_row_is_forbidden(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $notification = Notification::factory()->for($cleaner, 'notifiable')->for($tenant)->create();

        $this->post(route('notifications.read', $notification->id))->assertForbidden();
    }

    public function test_mark_read_unknown_uuid_returns_not_found(): void
    {
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);

        $this->post(route('notifications.read', (string) Str::uuid()))->assertNotFound();
    }

    public function test_mark_all_read_only_marks_own_active_tenant_unread_rows(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $tenant = Tenant::query()->findOrFail(current_tenant_id());
        $otherTenant = Tenant::factory()->create();

        $ownUnread = Notification::factory()->for($admin, 'notifiable')->for($tenant)->create();
        $otherTenantUnread = Notification::factory()->for($admin, 'notifiable')->for($otherTenant)->create();

        $response = $this->post(route('notifications.read-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success', __('app.notifications_all_marked_read'));
        $ownUnread->refresh();
        $otherTenantUnread->refresh();
        $this->assertNotNull($ownUnread->read_at);
        $this->assertNull($otherTenantUnread->read_at);
    }
}
