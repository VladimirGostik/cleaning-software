<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TaskDueNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class NullTenantNotificationTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->user = $this->member($this->alpha, 'Admin');
    }

    public function test_in_tenant_scope_includes_null_tenant_rows_for_any_tenant(): void
    {
        $scoped = Notification::factory()->for($this->user, 'notifiable')->for($this->alpha)->create();
        $global = Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null]);
        $foreign = Notification::factory()->for($this->user, 'notifiable')->for(Tenant::factory()->create())->create();

        $ids = Notification::query()->forRecipient($this->user)->inTenant($this->alpha->id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$scoped->id, $global->id], $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_null_tenant_notification_is_not_visible_to_another_recipient(): void
    {
        Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null]);
        $stranger = $this->member($this->alpha, 'Admin');

        $this->assertSame(0, app(NotificationService::class)->bell($stranger, $this->alpha->id)->unread_count);
    }

    public function test_null_tenant_notification_can_be_marked_read_in_any_firm(): void
    {
        $global = Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null]);
        $this->webAs($this->user, $this->alpha);

        $this->post(route('notifications.read', $global))->assertRedirect();

        $this->assertNotNull($global->refresh()->read_at);
    }

    public function test_task_notification_with_null_tenant_stores_a_null_tenant_row(): void
    {
        $task = Task::factory()->global()->createdBy($this->user)->create();

        $this->user->notify(new TaskDueNotification(null, $task->id, $task->title));

        $this->assertNull($this->user->notifications()->sole()->tenant_id);
    }

    public function test_mark_all_read_clears_null_tenant_rows_too(): void
    {
        Notification::factory()->for($this->user, 'notifiable')->create(['tenant_id' => null]);
        Notification::factory()->for($this->user, 'notifiable')->for($this->alpha)->create();

        $count = app(NotificationService::class)->markAllRead($this->user, $this->alpha->id);

        $this->assertSame(2, $count);
    }
}
