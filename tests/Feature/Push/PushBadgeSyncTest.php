<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Contracts\SendsPush;
use App\Jobs\SyncPushBadge;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TaskDueNotification;
use App\Services\DeviceTokenService;
use App\Services\NotificationService;
use App\Services\Push\PushMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Fakes\FakePushSender;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class PushBadgeSyncTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $user;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->user = $this->member($this->alpha, 'Vedúca');
        $this->member($this->beta, 'Vedúca', $this->user);
        $this->sender = new FakePushSender;
        $this->app->instance(SendsPush::class, $this->sender);
    }

    private function device(?User $user = null): void
    {
        DeviceToken::factory()->for($user ?? $this->user)->create();
    }

    /** @return list<Notification> */
    private function unread(User $user, ?Tenant $tenant, int $count = 1): array
    {
        return array_values(Notification::factory()->count($count)->create([
            'notifiable_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'read_at' => null,
        ])->all());
    }

    private function assertBadgeOnly(int $badge, int $index = 0): void
    {
        $payload = $this->sender->sent[$index]['message']->toFcmPayload();

        $this->assertArrayNotHasKey('notification', $payload);
        $this->assertSame('badge', Arr::get($payload, 'data.kind'));
        $this->assertSame($badge, Arr::get($payload, 'apns.payload.aps.badge'));
    }

    public function test_api_read_all_clears_every_phone_company_and_company_less_rows_and_syncs_badge(): void
    {
        $this->device();
        $rows = [...$this->unread($this->user, $this->alpha), ...$this->unread($this->user, $this->beta), ...$this->unread($this->user, null)];

        $this->apiAs($this->user, $this->alpha)->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 3);

        foreach ($rows as $row) {
            $this->assertNotNull($row->fresh()?->read_at);
        }

        $this->assertCount(1, $this->sender->sent);
        $this->assertBadgeOnly(0);
    }

    public function test_api_read_all_leaves_companies_outside_the_phone_scope_untouched(): void
    {
        $user = $this->member($this->alpha, 'Vedúca');
        $this->device($user);
        $inAlpha = $this->unread($user, $this->alpha)[0];
        $this->apiAs($user, $this->alpha);
        $gamma = $this->firm('Gamma');
        $this->member($this->beta, 'Sekretárka', $user);
        $this->member($gamma, 'Interná upratovačka', $user);
        $inBeta = $this->unread($user, $this->beta)[0];
        $inGamma = $this->unread($user, $gamma)[0];

        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->assertNotNull($inAlpha->fresh()?->read_at);
        $this->assertNull($inBeta->fresh()?->read_at);
        $this->assertNull($inGamma->fresh()?->read_at);
        $this->assertBadgeOnly(0);
    }

    public function test_web_read_all_stays_per_active_company(): void
    {
        $this->device();
        $this->unread($this->user, $this->alpha);
        $this->unread($this->user, null);
        $inBeta = $this->unread($this->user, $this->beta, 2);

        $this->webAs($this->user, $this->alpha)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertNull($inBeta[0]->fresh()?->read_at);
        $this->assertCount(1, $this->sender->sent);
        $this->assertBadgeOnly(2);
    }

    public function test_single_read_pushes_the_remaining_count_once(): void
    {
        $this->device();
        $rows = $this->unread($this->user, $this->alpha, 3);

        $this->apiAs($this->user, $this->alpha)->postJson("/api/notifications/{$rows[0]->id}/read")->assertNoContent();
        $this->assertCount(1, $this->sender->sent);
        $this->assertBadgeOnly(2);

        $this->postJson("/api/notifications/{$rows[0]->id}/read")->assertNoContent();
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_web_single_read_pushes_the_remaining_count(): void
    {
        $this->device();
        $rows = $this->unread($this->user, $this->alpha, 2);

        $this->webAs($this->user, $this->alpha)->post(route('notifications.read', $rows[0]))->assertRedirect();

        $this->assertBadgeOnly(1);
    }

    public function test_nothing_is_sent_without_device_tokens(): void
    {
        $this->unread($this->user, $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame([], $this->sender->sent);
    }

    public function test_badge_count_excludes_companies_without_mobile_access_or_view_notifications(): void
    {
        $user = $this->member($this->alpha, 'Sekretárka');
        $this->member($this->beta, 'Interná upratovačka', $user);
        $this->unread($user, $this->alpha);
        $this->unread($user, $this->beta);
        $this->unread($user, null);
        $this->consoleContext();

        $this->assertSame(0, app(NotificationService::class)->badgeCount($user));
    }

    public function test_badge_count_counts_company_less_rows_only_with_a_phone_company(): void
    {
        $this->unread($this->user, $this->alpha, 2);
        $this->unread($this->user, null);
        $this->unread($this->user, $this->beta);
        $this->apiAs($this->user, $this->alpha);
        $service = app(NotificationService::class);

        $this->assertSame(4, $service->badgeCount($this->user));

        $this->membershipOf($this->user, $this->beta)->update(['is_active' => false]);
        $this->assertSame(3, $service->badgeCount($this->user));
    }

    public function test_pending_row_adds_one_only_when_not_stored_and_in_scope(): void
    {
        $this->unread($this->user, $this->alpha);
        $this->apiAs($this->user, $this->alpha);
        $service = app(NotificationService::class);
        $pending = new TaskDueNotification($this->alpha->id, 'a0000000-0000-7000-8000-000000000001', 'Call');
        $pending->id = 'a0000000-0000-7000-8000-00000000000a';

        $this->assertSame(2, $service->badgeCount($this->user, $pending));

        $outside = new TaskDueNotification($this->firm('Gamma')->id, 'a0000000-0000-7000-8000-000000000001', 'Call');
        $outside->id = 'a0000000-0000-7000-8000-00000000000b';
        $this->assertSame(1, $service->badgeCount($this->user, $outside));

        $stored = $this->unread($this->user, null)[0];
        $storedPending = new TaskDueNotification(null, 'a0000000-0000-7000-8000-000000000001', 'Call');
        $storedPending->id = $stored->id;
        $this->assertSame(2, $service->badgeCount($this->user, $storedPending));
    }

    public function test_alert_push_still_reaches_the_cleaner_with_badge_zero(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $this->device($cleaner);
        $this->consoleContext();

        $cleaner->notify(new TaskDueNotification(null, 'a0000000-0000-7000-8000-000000000001', 'Call'));

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame(0, Arr::get($this->sender->sent[0]['message']->toFcmPayload(), 'apns.payload.aps.badge'));
        $this->assertArrayHasKey('notification', $this->sender->sent[0]['message']->toFcmPayload());
    }

    public function test_badge_only_payload_has_the_exact_shape(): void
    {
        $this->assertSame([
            'data' => ['kind' => 'badge'],
            'apns' => [
                'headers' => ['apns-push-type' => 'alert', 'apns-priority' => '10'],
                'payload' => ['aps' => ['badge' => 4]],
            ],
            'android' => ['priority' => 'normal'],
        ], PushMessage::badgeOnly(4)->toFcmPayload());
    }

    public function test_job_logs_and_rethrows_a_send_failure_without_content(): void
    {
        $this->device();
        $this->sender->throws = true;
        Log::shouldReceive('error')->once()->with('push.badge.failed', ['user_id' => $this->user->id]);

        try {
            (new SyncPushBadge($this->user->id))->handle(app(NotificationService::class), app(DeviceTokenService::class), $this->sender);
            $this->fail('expected the send failure to be rethrown');
        } catch (RuntimeException) {
        }
    }

    public function test_android_only_person_queues_nothing(): void
    {
        DeviceToken::factory()->android()->for($this->user)->create();
        Queue::fake();
        $this->unread($this->user, $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson('/api/notifications/read-all')->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_mixed_platforms_get_the_badge_only_message_on_ios_but_alerts_on_both(): void
    {
        DeviceToken::factory()->for($this->user)->create(['token' => 'ios-tok']);
        DeviceToken::factory()->android()->for($this->user)->create(['token' => 'and-tok']);
        $this->unread($this->user, $this->alpha);

        $this->apiAs($this->user, $this->alpha)->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(['ios-tok'], $this->sender->sent[0]['tokens']);

        $this->consoleContext();
        $this->user->notify(new TaskDueNotification($this->alpha->id, 'a0000000-0000-7000-8000-000000000001', 'Call'));

        $this->assertEqualsCanonicalizing(['ios-tok', 'and-tok'], $this->sender->sent[1]['tokens']);
    }

    public function test_unique_lock_blocks_a_second_dispatch_and_expires(): void
    {
        Queue::fake();

        SyncPushBadge::dispatch($this->user->id);
        SyncPushBadge::dispatch($this->user->id);
        Queue::assertPushed(SyncPushBadge::class, 1);

        $this->travel(301)->seconds();
        SyncPushBadge::dispatch($this->user->id);
        Queue::assertPushed(SyncPushBadge::class, 2);
    }

    public function test_job_prunes_unusable_tokens(): void
    {
        $this->device();
        $token = DeviceToken::query()->firstOrFail()->token;
        $this->sender->unusable = [$token];

        (new SyncPushBadge($this->user->id))->handle(app(NotificationService::class), app(DeviceTokenService::class), $this->sender);

        $this->assertSame(0, DeviceToken::query()->count());
    }

    public function test_job_does_nothing_for_a_missing_user_or_without_ios_tokens(): void
    {
        $service = app(NotificationService::class);
        $devices = app(DeviceTokenService::class);

        (new SyncPushBadge('019b0000-0000-7000-8000-000000000000'))->handle($service, $devices, $this->sender);
        (new SyncPushBadge($this->user->id))->handle($service, $devices, $this->sender);

        $this->assertSame([], $this->sender->sent);
    }

    public function test_read_all_on_phone_skips_a_deactivated_membership(): void
    {
        $inAlpha = $this->unread($this->user, $this->alpha)[0];
        $inBeta = $this->unread($this->user, $this->beta)[0];
        $this->membershipOf($this->user, $this->beta)->update(['is_active' => false]);
        $this->consoleContext();

        $this->assertSame(1, app(NotificationService::class)->markAllReadOnPhone($this->user));

        $this->assertNotNull($inAlpha->fresh()?->read_at);
        $this->assertNull($inBeta->fresh()?->read_at);
    }
}
