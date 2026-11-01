<?php

declare(strict_types=1);

namespace Tests\Unit\Scopes;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskCommentScopeTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    public function test_neither_a_tenant_nor_a_user_bound_sees_nothing(): void
    {
        $firm = $this->firm('A');
        $owner = User::factory()->create();
        $task = Task::factory()->forTenant($firm)->createdBy($owner)->create();
        TaskComment::factory()->forTask($task)->by($owner)->create();

        $this->consoleContext();

        $this->assertSame(0, TaskComment::query()->count());
        $this->assertSame(1, TaskComment::query()->withoutGlobalScopes()->count());
    }

    public function test_a_bound_tenant_sees_only_its_firm_rows(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $owner = User::factory()->create();
        $mine = TaskComment::factory()->forTask(Task::factory()->forTenant($a)->createdBy($owner)->create())->by($owner)->create();
        TaskComment::factory()->forTask(Task::factory()->forTenant($b)->createdBy($owner)->create())->by($owner)->create();

        $this->consoleContext();
        $this->bindTenant($a);

        $this->assertSame([$mine->id], TaskComment::query()->pluck('id')->all());
    }

    public function test_an_authenticated_user_sees_only_comments_on_their_own_personal_tasks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $own = TaskComment::factory()->forTask(Task::factory()->global()->createdBy($owner)->create())->by($owner)->create();
        TaskComment::factory()->forTask(Task::factory()->global()->createdBy($other)->create())->by($other)->create();

        $this->consoleContext();
        Auth::setUser($owner);

        $this->assertSame([$own->id], TaskComment::query()->pluck('id')->all());
    }
}
