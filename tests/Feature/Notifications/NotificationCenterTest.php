<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function insertNotification(User $user, string $tenantId, ?string $readAt = null): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\InvoiceOverdue',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'tenant_id' => $tenantId,
            'data' => json_encode([
                'type' => 'invoice.overdue',
                'title' => 'Test overdue',
                'body' => 'Test body',
                'url' => null,
                'meta' => [],
            ]),
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function test_index_lists_only_active_tenant_notifications(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Notification for active tenant
        $activeId = $this->insertNotification($user, $tenant->id);

        // Notification for a different tenant — must NOT appear
        $otherTenant = Tenant::factory()->create(['owner_id' => $user->id, 'is_active' => true]);
        $this->insertNotification($user, $otherTenant->id);

        $response = $this->get(route('notifications.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->where('notifications.data.0.id', $activeId)
            ->count('notifications.data', 1),
        );
    }

    public function test_unread_only_filter_returns_only_unread(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->insertNotification($user, $tenant->id, null);
        $this->insertNotification($user, $tenant->id, now()->toDateTimeString());

        $response = $this->get(route('notifications.index', ['filter' => ['unreadOnly' => '1']]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->count('notifications.data', 1),
        );
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $id = $this->insertNotification($user, $tenant->id);

        $this->post(route('notifications.read', $id))->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    public function test_mark_all_read_only_affects_active_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $id1 = $this->insertNotification($user, $tenant->id);
        $id2 = $this->insertNotification($user, $tenant->id);

        // Notification in other tenant — should remain unread
        $otherTenant = Tenant::factory()->create(['owner_id' => $user->id, 'is_active' => true]);
        $otherId = $this->insertNotification($user, $otherTenant->id);

        $this->post(route('notifications.read-all'))->assertRedirect();

        $this->assertNotNull(DB::table('notifications')->where('id', $id1)->value('read_at'));
        $this->assertNotNull(DB::table('notifications')->where('id', $id2)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $otherId)->value('read_at'));
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_index_denied_without_view_notifications_permission(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('notifications.index'))->assertForbidden();
    }

    public function test_mark_read_denied_for_another_users_notification(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $otherUser = User::factory()->create();
        $id = $this->insertNotification($otherUser, $tenant->id);

        $this->post(route('notifications.read', $id))->assertForbidden();
    }

    public function test_mark_read_denied_for_notification_in_different_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $otherTenant = Tenant::factory()->create(['owner_id' => $user->id, 'is_active' => true]);
        $id = $this->insertNotification($user, $otherTenant->id);

        $this->post(route('notifications.read', $id))->assertForbidden();
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $this->post(route('logout'));

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
