<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskListFiltersTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        /** @var list<string> $titles */
        $titles = $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks?'.$query)->assertOk()->json('data.*.title');

        return $titles;
    }

    /** @param  array<string, mixed>  $attributes */
    private function task(string $title, array $attributes = [], ?Tenant $tenant = null): Task
    {
        return Task::factory()->forTenant($tenant ?? $this->alpha)->createdBy($this->admin)->create(['title' => $title, ...$attributes]);
    }

    public function test_assignee_me_spans_the_actors_memberships_across_companies(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $this->task('mine-alpha', ['assigned_membership_id' => $this->membershipOf($this->admin, $this->alpha)->id]);
        $this->task('mine-beta', ['assigned_membership_id' => $this->membershipOf($this->admin, $this->beta)->id], $this->beta);
        $this->task('theirs', ['assigned_membership_id' => $this->membershipOf($other, $this->alpha)->id]);
        $this->task('nobody');

        $this->assertEqualsCanonicalizing(['mine-alpha', 'mine-beta'], $this->titles('scope=all&filter[assignee]=me'));
        $this->assertSame(['mine-alpha'], $this->titles('filter[assignee]=me'));
    }

    public function test_assignee_none_and_membership_id(): void
    {
        $other = $this->member($this->alpha, 'Admin');
        $membership = $this->membershipOf($other, $this->alpha);
        $this->task('theirs', ['assigned_membership_id' => $membership->id]);
        $this->task('nobody');

        $this->assertSame(['nobody'], $this->titles('filter[assignee]=none'));
        $this->assertSame(['theirs'], $this->titles('filter[assignee]='.$membership->id));
        $this->assertEqualsCanonicalizing(['theirs', 'nobody'], $this->titles('filter[assignee]=garbage'));
    }

    public function test_due_filter_uses_local_days(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->task('today-late', ['due_at' => '2026-11-10 22:30:00']);
        $this->task('next-day-early', ['due_at' => '2026-11-10 23:30:00']);
        $this->task('yesterday', ['due_at' => '2026-11-09 10:00:00']);
        $this->task('in-three-days', ['due_at' => '2026-11-13 10:00:00']);
        $this->task('in-nine-days', ['due_at' => '2026-11-19 10:00:00']);
        $this->task('no-due');
        $this->task('done-yesterday', ['due_at' => '2026-11-09 10:00:00', 'status' => TaskStatusEnum::Done]);

        $this->assertSame(['today-late'], $this->titles('filter[due]=today'));
        $this->assertEqualsCanonicalizing(['yesterday'], $this->titles('filter[due]=overdue'));
        $this->assertEqualsCanonicalizing(['today-late', 'next-day-early', 'in-three-days'], $this->titles('filter[due]=week'));
        $this->assertEqualsCanonicalizing(['no-due'], $this->titles('filter[due]=none'));
    }

    public function test_status_accepts_a_comma_list_and_unknown_values_return_nothing(): void
    {
        $this->task('open');
        $this->task('doing', ['status' => TaskStatusEnum::InProgress]);
        $this->task('nice', ['status' => TaskStatusEnum::NiceToHave]);
        $this->task('finished', ['status' => TaskStatusEnum::Done]);

        $this->assertEqualsCanonicalizing(['open', 'doing', 'nice'], $this->titles('filter[status]=open,in_progress,waiting,nice_to_have'));
        $this->assertSame(['open'], $this->titles('filter[status]=open'));
        $this->assertSame([], $this->titles('filter[status]=cancelled'));
    }

    public function test_priority_sort_orders_high_to_low_and_reverses(): void
    {
        $this->task('low', ['priority' => TaskPriorityEnum::Low]);
        $this->task('high', ['priority' => TaskPriorityEnum::High]);
        $this->task('normal', ['priority' => TaskPriorityEnum::Normal]);

        $this->assertSame(['high', 'normal', 'low'], $this->titles('sort=priority'));
        $this->assertSame(['low', 'normal', 'high'], $this->titles('sort=-priority'));
    }
}
