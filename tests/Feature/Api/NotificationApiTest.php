<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Vedúca');
        $this->member($this->beta, 'Vedúca', $this->user);
    }

    /** @param array<string, mixed> $attributes */
    private function notificationFor(User $user, ?Tenant $tenant, array $attributes = []): Notification
    {
        return Notification::factory()->create([
            'notifiable_id' => $user->id,
            'tenant_id' => $tenant?->id,
            ...$attributes,
        ]);
    }

    public function test_lists_own_notifications_of_the_active_firm_and_company_less_ones(): void
    {
        $inAlpha = $this->notificationFor($this->user, $this->alpha);
        $global = $this->notificationFor($this->user, null);
        $inBeta = $this->notificationFor($this->user, $this->beta);
        $other = $this->notificationFor($this->member($this->alpha, 'Vedúca'), $this->alpha);

        $ids = (array) $this->apiAs($this->user, $this->alpha)->getJson('/api/notifications')
            ->assertOk()
            ->json('data.*.id');

        $this->assertEqualsCanonicalizing([$inAlpha->id, $global->id], $ids);
        $this->assertNotContains($inBeta->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_filters_unread(): void
    {
        $unread = $this->notificationFor($this->user, $this->alpha);
        $this->notificationFor($this->user, $this->alpha, ['read_at' => now()]);

        $ids = $this->apiAs($this->user, $this->alpha)->getJson('/api/notifications?filter[read]=0')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$unread->id], $ids);
    }

    public function test_marks_own_notification_read(): void
    {
        $notification = $this->notificationFor($this->user, $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson("/api/notifications/{$notification->id}/read")->assertNoContent();

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_marks_company_less_notification_read(): void
    {
        $notification = $this->notificationFor($this->user, null);

        $this->apiAs($this->user, $this->alpha)->postJson("/api/notifications/{$notification->id}/read")->assertNoContent();

        $this->assertNotNull($notification->fresh()?->read_at);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $other = $this->member($this->alpha, 'Vedúca');
        $notification = $this->notificationFor($other, $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson("/api/notifications/{$notification->id}/read")->assertForbidden();

        $this->assertNull($notification->fresh()?->read_at);
    }

    public function test_cannot_mark_a_notification_of_another_firm(): void
    {
        $notification = $this->notificationFor($this->user, $this->beta);

        $this->apiAs($this->user, $this->alpha)->postJson("/api/notifications/{$notification->id}/read")->assertForbidden();

        $this->assertNull($notification->fresh()?->read_at);
    }

    public function test_unknown_notification_is_not_found(): void
    {
        $this->apiAs($this->user, $this->alpha)
            ->postJson('/api/notifications/019b0000-0000-7000-8000-000000000000/read')
            ->assertNotFound();
    }

    public function test_read_all_marks_active_firm_and_company_less_rows_only(): void
    {
        $inAlpha = $this->notificationFor($this->user, $this->alpha);
        $global = $this->notificationFor($this->user, null);
        $inBeta = $this->notificationFor($this->user, $this->beta);
        $other = $this->notificationFor($this->member($this->alpha, 'Vedúca'), $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertNotNull($inAlpha->fresh()?->read_at);
        $this->assertNotNull($global->fresh()?->read_at);
        $this->assertNull($inBeta->fresh()?->read_at);
        $this->assertNull($other->fresh()?->read_at);
    }

    public function test_role_without_view_notifications_is_forbidden(): void
    {
        $this->apiAs($this->member($this->alpha, 'Interná upratovačka'), $this->alpha)
            ->getJson('/api/notifications')
            ->assertForbidden();
    }

    public function test_header_for_a_firm_without_membership_is_forbidden(): void
    {
        $gamma = $this->firm('Gamma');

        $this->apiAs($this->user, $gamma)->getJson('/api/notifications')->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }

    public function test_list_rows_carry_the_mobile_route(): void
    {
        $invoiceId = '019b0000-0000-7000-8000-0000000000aa';
        $this->notificationFor($this->user, $this->alpha, ['data' => ['title' => 't', 'body' => 'b', 'url' => "/invoices/{$invoiceId}", 'meta' => []]]);
        $this->notificationFor($this->user, $this->alpha, ['data' => ['title' => 't', 'body' => 'b', 'url' => '/clients/abc', 'meta' => []]]);

        $routes = (array) $this->apiAs($this->user, $this->alpha)->getJson('/api/notifications')->assertOk()->json('data.*.route');

        $this->assertEqualsCanonicalizing(["/invoices/{$invoiceId}", '/notifications'], $routes);
    }

    public function test_per_page_is_clamped(): void
    {
        $this->apiAs($this->user, $this->alpha)->getJson('/api/notifications?per_page=-5')->assertOk()->assertJsonPath('per_page', 1);
        $this->apiAs($this->user, $this->alpha)->getJson('/api/notifications?per_page=100000')->assertOk()->assertJsonPath('per_page', 100);
    }
}
