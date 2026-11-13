<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\ActionChannelEnum;
use App\Enums\ConfirmationChannelEnum;
use App\Enums\PendingActionEnum;
use App\Models\PendingAction;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ActionConfirmationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskDeleteThroughAiTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->lead = $this->member($this->firm, 'Vedúca');
    }

    private function prepare(User $user, Task $task): PendingAction
    {
        $prepared = app(ActionConfirmationService::class)->prepare($user, $this->firm->id, PendingActionEnum::DeleteTask, $task, [], ActionChannelEnum::Assistant);

        return PendingAction::query()->findOrFail($prepared->pending_action->id);
    }

    public function test_creator_without_delete_permission_prepares_and_confirms_in_the_app(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();

        $pending = $this->prepare($this->lead, $task);

        $this->webAs($this->lead, $this->firm)->post(route('pending-actions.confirm', $pending))->assertRedirect();

        $this->assertNull(Task::unscopedForSystem()->find($task->id));
    }

    public function test_preparing_for_another_users_task_is_refused(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->member($this->firm, 'Vedúca'))->create();

        try {
            $this->prepare($this->lead, $task);
            $this->fail('Prepare should have been refused.');
        } catch (AuthorizationException) {
            $this->assertSame(0, PendingAction::query()->count());
            $this->assertNotNull(Task::unscopedForSystem()->find($task->id));
        }
    }

    public function test_delete_task_cannot_be_confirmed_over_the_chat_channel(): void
    {
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->lead)->create();
        $pending = $this->prepare($this->lead, $task);

        $this->expectException(ValidationException::class);

        try {
            app(ActionConfirmationService::class)->execute($this->lead, $pending, ConfirmationChannelEnum::Chat);
        } finally {
            $this->assertNotNull(Task::unscopedForSystem()->find($task->id));
        }
    }
}
