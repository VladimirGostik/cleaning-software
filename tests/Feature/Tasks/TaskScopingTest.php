<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskScopingTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $firm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->firm = $this->firm('Alpha');
        $this->admin = $this->member($this->firm, 'Admin');
    }

    /** @return list<string> */
    private function listedIds(User $user): array
    {
        $this->webAs($user, $this->firm);
        /** @var list<string> $ids */
        $ids = [];
        $this->withoutVite()->get('/tasks')->assertInertia(function (AssertableInertia $page) use (&$ids): void {
            /** @var array{tasks: array{data: list<array{id: string}>}} $props */
            $props = $page->toArray()['props'];
            $ids = array_column($props['tasks']['data'], 'id');
        });

        return $ids;
    }

    public function test_user_without_view_all_sees_only_created_or_assigned_firm_tasks(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $created = Task::factory()->forTenant($this->firm)->createdBy($secretary)->create();
        $assigned = Task::factory()->assignedTo($this->membershipOf($secretary, $this->firm))->createdBy($this->admin)->create();
        Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        Task::factory()->global()->createdBy($this->admin)->create();

        $this->assertEqualsCanonicalizing([$created->id, $assigned->id], $this->listedIds($secretary));
    }

    public function test_user_with_view_all_sees_every_firm_task_but_no_foreign_globals(): void
    {
        $lead = $this->member($this->firm, 'Vedúca');
        $one = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $two = Task::factory()->forTenant($this->firm)->createdBy($lead)->create();
        $ownGlobal = Task::factory()->global()->createdBy($lead)->create();
        Task::factory()->global()->createdBy($this->admin)->create();

        $this->assertEqualsCanonicalizing([$one->id, $two->id, $ownGlobal->id], $this->listedIds($lead));
    }

    public function test_hidden_firm_task_is_forbidden_for_a_user_without_view_all(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $hidden = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($secretary, $this->firm);

        $this->get(route('tasks.show', $hidden))->assertForbidden();
    }

    public function test_secretary_edits_but_cannot_delete_without_the_delete_permission(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $task = Task::factory()->forTenant($this->firm)->createdBy($secretary)->create();
        $this->webAs($secretary, $this->firm);

        $this->put(route('tasks.update', $task), ['title' => 'Edited', 'priority' => 'low'])->assertRedirect();
        $this->delete(route('tasks.destroy', $task))->assertForbidden();
    }

    public function test_assigning_a_firm_task_notifies_the_assignee_once(): void
    {
        Notification::fake();
        $secretary = $this->member($this->firm, 'Sekretárka');
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->post(route('tasks.assign', $task), ['assigned_membership_id' => $this->membershipOf($secretary, $this->firm)->id])->assertRedirect();
        $this->post(route('tasks.assign', $task), ['assigned_membership_id' => $this->membershipOf($secretary, $this->firm)->id])->assertRedirect();

        Notification::assertSentToTimes($secretary, TaskAssignedNotification::class, 1);
        $this->assertSame($this->membershipOf($secretary, $this->firm)->id, $task->refresh()->assigned_membership_id);
    }

    public function test_assigning_to_yourself_does_not_notify(): void
    {
        Notification::fake();
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->post(route('tasks.assign', $task), ['assigned_membership_id' => $this->membershipOf($this->admin, $this->firm)->id])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_assignee_must_be_an_active_member_of_the_task_firm(): void
    {
        $beta = $this->firm('Beta');
        $outsider = $this->member($beta, 'Sekretárka');
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($this->admin, $this->firm);

        $this->postJson(route('tasks.assign', $task), ['assigned_membership_id' => $this->membershipOf($outsider, $beta)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_membership_id');
    }

    public function test_user_without_task_permissions_cannot_assign(): void
    {
        $cleaner = $this->member($this->firm, 'Interná upratovačka');
        $task = Task::factory()->forTenant($this->firm)->createdBy($this->admin)->create();
        $this->webAs($cleaner, $this->firm);

        $this->post(route('tasks.assign', $task), ['assigned_membership_id' => null])->assertForbidden();
    }

    public function test_store_with_assignee_notifies_and_stores_the_membership(): void
    {
        Notification::fake();
        $secretary = $this->member($this->firm, 'Sekretárka');
        $this->webAs($this->admin, $this->firm);

        $this->post('/tasks', [
            'title' => 'Prepare offer', 'priority' => 'normal',
            'assigned_membership_id' => $this->membershipOf($secretary, $this->firm)->id,
        ])->assertRedirect();

        $this->assertSame($this->membershipOf($secretary, $this->firm)->id, Task::query()->sole()->assigned_membership_id);
        Notification::assertSentToTimes($secretary, TaskAssignedNotification::class, 1);
    }

    public function test_deactivating_an_employee_unassigns_open_tasks_only(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $membership = $this->membershipOf($secretary, $this->firm);
        $open = Task::factory()->assignedTo($membership)->createdBy($this->admin)->create();
        $done = Task::factory()->assignedTo($membership)->createdBy($this->admin)->done()->create();
        $this->webAs($this->admin, $this->firm);

        $this->post(route('employees.deactivate', $membership))->assertRedirect();

        $this->assertNull($open->refresh()->assigned_membership_id);
        $this->assertSame($membership->id, $done->refresh()->assigned_membership_id);
    }

    public function test_user_service_delete_releases_done_and_trashed_assignments(): void
    {
        $secretary = $this->member($this->firm, 'Sekretárka');
        $membership = $this->membershipOf($secretary, $this->firm);
        $done = Task::factory()->assignedTo($membership)->createdBy($this->admin)->done()->create();
        $trashed = Task::factory()->assignedTo($membership)->createdBy($this->admin)->create();
        $trashed->delete();
        $this->webAs($this->admin, $this->firm);
        $this->bindTenant($this->firm);

        app(UserService::class)->delete($secretary);

        $this->assertNull(Task::unscopedForSystem()->findOrFail($done->id)->assigned_membership_id);
        $this->assertNull(Task::unscopedForSystem()->withTrashed()->findOrFail($trashed->id)->assigned_membership_id);
    }
}
