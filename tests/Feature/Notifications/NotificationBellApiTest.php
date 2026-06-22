<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationBellApiTest extends TestCase
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
                'title' => 'Test',
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

    public function test_bell_returns_unread_count_and_recent(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->insertNotification($user, $tenant->id, null);
        $this->insertNotification($user, $tenant->id, null);
        $this->insertNotification($user, $tenant->id, now()->toDateTimeString()); // read

        $response = $this->getJson(route('api.notifications.bell'));

        $response->assertOk();
        $response->assertJsonStructure(['unreadCount', 'recent']);
        $this->assertEquals(2, $response->json('unreadCount'));
        $this->assertCount(3, $response->json('recent'));
    }

    public function test_bell_is_scoped_to_active_tenant(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Active tenant notification
        $this->insertNotification($user, $tenant->id);

        // Other tenant notification — must not appear
        $otherTenant = Tenant::factory()->create(['owner_id' => $user->id, 'is_active' => true]);
        $this->insertNotification($user, $otherTenant->id);

        $response = $this->getJson(route('api.notifications.bell'));

        $response->assertOk();
        $this->assertCount(1, $response->json('recent'));
    }

    public function test_bell_caps_recent_at_five(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        for ($i = 0; $i < 7; $i++) {
            $this->insertNotification($user, $tenant->id);
        }

        $response = $this->getJson(route('api.notifications.bell'));

        $response->assertOk();
        $this->assertCount(5, $response->json('recent'));
        $this->assertEquals(7, $response->json('unreadCount'));
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_bell_requires_authentication(): void
    {
        $this->post(route('logout'));

        $this->getJson(route('api.notifications.bell'))->assertUnauthorized();
    }
}
