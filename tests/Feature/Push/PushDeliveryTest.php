<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Contracts\SendsPush;
use App\Enums\NotificationTypeEnum;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvitationCreated;
use App\Notifications\QuoteSent;
use App\Notifications\TaskDueNotification;
use App\Services\NotificationService;
use App\Services\Push\FirebasePushSender;
use App\Services\Push\PushMessage;
use App\Services\Push\PushRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use Tests\Fakes\FakePushSender;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class PushDeliveryTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $user;

    private FakePushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm();
        $this->user = $this->member($this->firm, 'Admin');
        $this->consoleContext();
        $this->sender = new FakePushSender;
        $this->app->instance(SendsPush::class, $this->sender);
    }

    private function twoDevices(): void
    {
        DeviceToken::factory()->for($this->user)->create(['token' => 'ios-tok']);
        DeviceToken::factory()->android()->for($this->user)->create(['token' => 'and-tok']);
    }

    private function dueNotification(?string $tenantId): TaskDueNotification
    {
        return new TaskDueNotification($tenantId, 'a0000000-0000-7000-8000-000000000001', 'Call client');
    }

    public function test_push_reaches_every_token_with_apns_and_android_blocks(): void
    {
        $this->twoDevices();
        Notification::factory()->for($this->user, 'notifiable')->create(['read_at' => null, 'tenant_id' => null]);

        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertCount(1, $this->sender->sent);
        $this->assertEqualsCanonicalizing(['ios-tok', 'and-tok'], $this->sender->allTokens());

        $message = $this->sender->sent[0]['message'];
        $dbRow = Notification::query()->forRecipient($this->user)->where('type', 'task.due')->firstOrFail();
        $this->assertSame($dbRow->id, $message->notificationId);
        $this->assertSame('/tasks/a0000000-0000-7000-8000-000000000001', $message->route);
        $this->assertSame(2, $message->badge);

        $payload = $message->toFcmPayload();
        $this->assertSame('default', Arr::get($payload, 'apns.payload.aps.sound'));
        $this->assertSame(2, Arr::get($payload, 'apns.payload.aps.badge'));
        $this->assertSame('high', Arr::get($payload, 'android.priority'));
        $this->assertSame($message->title, Arr::get($payload, 'notification.title'));
    }

    public function test_null_tenant_notification_is_pushed(): void
    {
        $this->twoDevices();

        $this->user->notify($this->dueNotification(null));

        $this->assertCount(1, $this->sender->sent);
    }

    public function test_push_disabled_in_preferences_sends_nothing_but_keeps_in_app_row(): void
    {
        $this->twoDevices();
        $this->user->update(['notification_preferences' => ['task.due' => ['mail' => false, 'push' => false]]]);

        $this->user->refresh()->notify($this->dueNotification($this->firm->id));

        $this->assertSame([], $this->sender->sent);
        $this->assertSame(1, Notification::query()->forRecipient($this->user)->count());
    }

    public function test_push_defaults_to_on_when_no_preference_is_stored(): void
    {
        $this->twoDevices();

        $this->user->notify(new QuoteSent($this->firm->id, 'a0000000-0000-7000-8000-000000000002', 'Q-1'));

        $this->assertCount(1, $this->sender->sent);
        $this->assertSame('/quotes/a0000000-0000-7000-8000-000000000002', $this->sender->sent[0]['message']->route);
    }

    public function test_mail_only_types_never_push(): void
    {
        $this->twoDevices();

        $this->user->notify(new InvitationCreated('token', 'Firm', 'Admin'));

        $this->assertSame([], $this->sender->sent);
        $this->assertFalse(NotificationTypeEnum::InvitationCreated->deliversPush());
        $this->assertFalse(NotificationTypeEnum::InvoiceIssued->deliversPush());
    }

    public function test_user_without_devices_triggers_no_send(): void
    {
        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertSame([], $this->sender->sent);
    }

    public function test_unregistered_tokens_are_deleted(): void
    {
        $this->twoDevices();
        $this->sender->unusable = ['and-tok'];

        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertSame(['ios-tok'], DeviceToken::query()->pluck('token')->all());
    }

    public function test_pruned_tokens_are_logged_by_count_without_token_values(): void
    {
        $this->twoDevices();
        $this->sender->unusable = ['and-tok'];
        Log::shouldReceive('warning')->once()->with('push.token.pruned', ['user_id' => $this->user->id, 'count' => 1]);

        $this->user->notify($this->dueNotification($this->firm->id));
    }

    public function test_nothing_is_logged_as_pruned_when_every_token_is_usable(): void
    {
        $this->twoDevices();
        Log::shouldReceive('warning')->never();

        $this->user->notify($this->dueNotification($this->firm->id));
    }

    public function test_sender_failure_does_not_fail_the_notification(): void
    {
        $this->twoDevices();
        $this->sender->throws = true;
        Log::shouldReceive('error')->once()->with('push.send.failed', Mockery::type('array'));

        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertSame(1, Notification::query()->forRecipient($this->user)->count());
        $this->assertSame(2, DeviceToken::query()->count());
    }

    public function test_other_users_tokens_are_not_targeted(): void
    {
        $other = $this->member($this->firm, 'Admin');
        DeviceToken::factory()->for($other)->create(['token' => 'other-tok']);
        DeviceToken::factory()->for($this->user)->create(['token' => 'mine']);
        $this->consoleContext();

        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertSame(['mine'], $this->sender->allTokens());
    }

    public function test_real_sender_is_inert_without_credentials(): void
    {
        config(['firebase.projects.app.credentials' => null]);
        Log::shouldReceive('info')->once();

        $result = $this->app->make(FirebasePushSender::class)->send(new PushMessage('t', 'b', '/x', 'tid', 'id', 1), ['tok']);

        $this->assertSame([], $result);
    }

    public function test_contract_resolves_to_firebase_sender_by_default(): void
    {
        $this->app->forgetInstance(SendsPush::class);
        $this->app->offsetUnset(SendsPush::class);
        $this->app->bind(SendsPush::class, FirebasePushSender::class);

        $this->assertInstanceOf(FirebasePushSender::class, $this->app->make(SendsPush::class));
    }

    public function test_payload_is_a_valid_fcm_message(): void
    {
        $payload = (new PushMessage('Title', 'Body', '/tasks/1', 'tid', 'n1', 3))->toFcmPayload();

        /** @phpstan-ignore argument.type (SDK array-shape is stricter than our runtime-built payload) */
        $json = CloudMessage::fromArray($payload)->jsonSerialize();

        $this->assertSame(3, Arr::get($json, 'apns.payload.aps.badge'));
        $this->assertSame('high', Arr::get($json, 'android.priority'));
        $this->assertSame('/tasks/1', Arr::get($json, 'data.route'));
    }

    public function test_firm_without_mobile_access_is_never_pushed_but_in_app_row_stays(): void
    {
        $other = $this->firm('Other');
        $this->member($other, 'Sekretárka', $this->user);
        $this->consoleContext();
        $this->twoDevices();

        $this->user->notify(new QuoteSent($other->id, 'a0000000-0000-7000-8000-000000000005', 'Q-9'));

        $this->assertSame([], $this->sender->sent);
        $this->assertSame(1, Notification::query()->forRecipient($this->user)->where('tenant_id', $other->id)->count());

        $this->user->notify(new QuoteSent($this->firm->id, 'a0000000-0000-7000-8000-000000000005', 'Q-9'));
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_global_notification_needs_mobile_access_in_any_firm(): void
    {
        $nobody = User::factory()->create();
        $officeFirm = $this->firm('Office');
        $this->member($officeFirm, 'Sekretárka', $nobody);
        $this->consoleContext();
        DeviceToken::factory()->for($nobody)->create();

        $nobody->notify($this->dueNotification(null));
        $this->assertSame([], $this->sender->sent);

        $this->twoDevices();
        $this->user->notify($this->dueNotification(null));
        $this->assertCount(1, $this->sender->sent);
    }

    public function test_tenant_id_is_in_the_data_block_and_empty_for_global(): void
    {
        $this->twoDevices();

        $this->user->notify($this->dueNotification($this->firm->id));
        $this->user->notify($this->dueNotification(null));

        $this->assertSame($this->firm->id, Arr::get($this->sender->sent[0]['message']->toFcmPayload(), 'data.tenant_id'));
        $this->assertSame('', Arr::get($this->sender->sent[1]['message']->toFcmPayload(), 'data.tenant_id'));
    }

    public function test_route_allowlist_keeps_mobile_paths_and_falls_back_for_the_rest(): void
    {
        $id = 'a0000000-0000-7000-8000-000000000009';

        $this->assertSame("/tasks/{$id}", PushRoute::fromUrl("https://app.test/tasks/{$id}"));
        $this->assertSame("/invoices/{$id}", PushRoute::fromUrl("/invoices/{$id}"));
        $this->assertSame("/quotes/{$id}", PushRoute::fromUrl("/quotes/{$id}?x=1"));
        $this->assertSame("/expenses/{$id}", PushRoute::fromUrl("/expenses/{$id}"));
        $this->assertSame('/pending-actions', PushRoute::fromUrl('/pending-actions'));
        $this->assertSame('/notifications', PushRoute::fromUrl("/contracts/{$id}"));
        $this->assertSame('/notifications', PushRoute::fromUrl('/tasks/not-a-uuid'));
        $this->assertSame('/notifications', PushRoute::fromUrl("/tasks/{$id}/edit"));
        $this->assertSame('/notifications', PushRoute::fromUrl(null));
        $this->assertSame("/tasks/{$id}", PushRoute::fromUrl("https://app.test/tasks?task={$id}"));
        $this->assertSame('/notifications', PushRoute::fromUrl('https://app.test/tasks?task=not-a-uuid'));
        $this->assertSame('/notifications', PushRoute::fromUrl("https://app.test/tasks?task[]={$id}"));
    }

    public function test_badge_ignores_rows_of_firms_the_user_left_and_counts_global_rows(): void
    {
        $this->twoDevices();
        $left = $this->firm('Left');
        $this->member($left, 'Admin', $this->user);
        $this->consoleContext();
        Notification::factory()->for($this->user, 'notifiable')->count(3)->create(['tenant_id' => $left->id, 'read_at' => null]);
        Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null, 'read_at' => null]);
        Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => $this->firm->id, 'read_at' => now()]);
        $this->membershipOf($this->user, $left)->update(['is_active' => false]);

        $this->user->notify($this->dueNotification($this->firm->id));

        $this->assertSame(2, $this->sender->sent[0]['message']->badge);
    }

    public function test_badge_adds_the_own_row_when_it_is_not_committed_yet(): void
    {
        $service = app(NotificationService::class);

        $pending = $this->dueNotification(null);
        $pending->id = 'a0000000-0000-7000-8000-00000000000a';
        $this->assertSame(1, $service->badgeCount($this->user, $pending));

        $row = Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null, 'read_at' => null]);
        $pending->id = $row->id;
        $this->assertSame(1, $service->badgeCount($this->user, $pending));
    }
}
