<?php

declare(strict_types=1);

namespace Tests\Unit\Scopes;

use App\Models\Task;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskScopeTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    public function test_bound_tenant_without_user_sees_only_that_firm(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $creator = User::factory()->create();
        $mine = Task::factory()->forTenant($a)->createdBy($creator)->create();
        Task::factory()->forTenant($b)->createdBy($creator)->create();
        Task::factory()->global()->createdBy($creator)->create();

        $this->consoleContext();
        $this->bindTenant($a);

        $this->assertSame([$mine->id], Task::query()->pluck('id')->all());
    }

    public function test_authenticated_user_sees_own_global_tasks_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $own = Task::factory()->global()->createdBy($owner)->create();
        Task::factory()->global()->createdBy($other)->create();

        $this->consoleContext();
        Auth::setUser($owner);

        $this->assertSame([$own->id], Task::query()->pluck('id')->all());
        $this->assertSame(2, Task::unscopedForSystem()->count());
    }

    public function test_no_tenant_and_no_user_yields_nothing(): void
    {
        $a = $this->firm('A');
        Task::factory()->forTenant($a)->create();
        Task::factory()->global()->create();

        $this->consoleContext();

        $this->assertSame(0, Task::query()->count());
        $this->assertSame(2, Task::unscopedForSystem()->count());
    }

    public function test_tenant_branch_and_global_branch_combine_without_leaking_other_users_globals(): void
    {
        $a = $this->firm('A');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $firmTask = Task::factory()->forTenant($a)->createdBy($other)->create();
        $own = Task::factory()->global()->createdBy($owner)->create();
        Task::factory()->global()->createdBy($other)->create();

        $this->consoleContext();
        $this->bindTenant($a);
        Auth::setUser($owner);

        $this->assertEqualsCanonicalizing([$firmTask->id, $own->id], Task::query()->pluck('id')->all());
    }

    public function test_system_bypass_has_exactly_one_call_site(): void
    {
        $hits = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php'
                && str_contains((string) file_get_contents($file->getPathname()), 'withoutGlobalScope(TaskScope')) {
                $hits[] = $file->getPathname();
            }
        }

        $this->assertCount(1, $hits);
        $this->assertStringEndsWith('Models/Task.php', $hits[0]);
    }
}
