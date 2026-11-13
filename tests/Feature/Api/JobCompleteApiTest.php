<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\JobStatusEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class JobCompleteApiTest extends TestCase
{
    use CreatesTaskFixtures, RefreshDatabase;

    private function jobIn(Tenant $tenant, ?User $assignee, string $date = '2026-11-10', JobStatusEnum $status = JobStatusEnum::Planned): ScheduledJob
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $factory = ScheduledJob::factory()->forObject($object);

        if ($assignee !== null) {
            $factory = $factory->assignedTo($this->membershipOf($assignee, $tenant));
        }

        return $factory->state(['status' => $status])->create(['tenant_id' => $tenant->id, 'scheduled_date' => $date]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissionCatalog();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(10, 0));
    }

    public function test_assignee_completes_a_job_dated_today_and_a_repeat_changes_nothing(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $job = $this->jobIn($a, $user);

        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('can_complete', false);

        $stored = $job->fresh();
        $this->assertNotNull($stored);
        $this->assertSame(JobStatusEnum::Completed, $stored->status);
        $this->assertNull($stored->started_at);
        $completedAt = $stored->completed_at;
        $activities = Activity::query()->where('subject_id', $job->id)->count();

        $this->travel(5)->minutes();
        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $again = $job->fresh();
        $this->assertNotNull($again);
        $this->assertEquals($completedAt, $again->completed_at);
        $this->assertSame($activities, Activity::query()->where('subject_id', $job->id)->count());
    }

    public function test_second_call_on_a_stale_instance_writes_nothing(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $job = $this->jobIn($a, $user);
        $service = app(JobService::class);
        $first = ScheduledJob::query()->findOrFail($job->id);
        $stale = ScheduledJob::query()->findOrFail($job->id);

        $service->complete($first);
        $completedAt = $first->fresh()?->completed_at;
        $activities = Activity::query()->where('subject_id', $job->id)->count();

        $this->travel(5)->minutes();
        $result = $service->complete($stale);

        $this->assertSame(JobStatusEnum::Completed, $result->status);
        $this->assertEquals($completedAt, ScheduledJob::query()->findOrFail($job->id)->completed_at);
        $this->assertSame($activities, Activity::query()->where('subject_id', $job->id)->count());
    }

    public function test_overdue_planned_job_can_be_completed(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $job = $this->jobIn($a, $user, '2026-11-01');

        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")->assertOk();
    }

    public function test_future_job_is_refused(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $job = $this->jobIn($a, $user, '2026-11-11');

        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")->assertForbidden();
        $this->assertSame(JobStatusEnum::Planned, $job->fresh()?->status);
    }

    public function test_another_cleaners_job_is_refused_also_when_completed(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');
        $other = $this->member($a, 'Interná upratovačka');

        $planned = $this->jobIn($a, $other);
        $completed = $this->jobIn($a, $other, status: JobStatusEnum::Completed);

        $this->apiAs($user, $a)->postJson("/api/jobs/{$planned->id}/complete")->assertForbidden();
        $this->apiAs($user, $a)->postJson("/api/jobs/{$completed->id}/complete")->assertForbidden();
    }

    public function test_cancelled_and_unassigned_jobs_are_refused(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Interná upratovačka');

        $cancelled = $this->jobIn($a, $user, status: JobStatusEnum::Cancelled);
        $unassigned = $this->jobIn($a, null);

        $this->apiAs($user, $a)->postJson("/api/jobs/{$cancelled->id}/complete")->assertForbidden();
        $this->apiAs($user, $a)->postJson("/api/jobs/{$unassigned->id}/complete")->assertForbidden();
    }

    public function test_job_of_another_firm_is_not_found(): void
    {
        $a = $this->firm('A');
        $b = $this->firm('B');
        $user = $this->member($a, 'Interná upratovačka');
        $foreign = $this->member($b, 'Interná upratovačka');
        $job = $this->jobIn($b, $foreign);

        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")->assertNotFound();
    }

    public function test_assignee_without_record_own_work_is_refused(): void
    {
        $a = $this->firm('A');
        $user = $this->member($a, 'Zákazník');
        $job = $this->jobIn($a, $user);

        $this->apiAs($user, $a)->postJson("/api/jobs/{$job->id}/complete")->assertForbidden();
    }

    public function test_editor_can_complete_an_in_progress_job_and_repeat_on_a_completed_one(): void
    {
        $a = $this->firm('A');
        $admin = $this->member($a, 'Admin');
        $job = $this->jobIn($a, null, status: JobStatusEnum::InProgress);

        $this->apiAs($admin, $a)->postJson("/api/jobs/{$job->id}/complete")->assertOk()->assertJsonPath('status', 'completed');
        $this->apiAs($admin, $a)->postJson("/api/jobs/{$job->id}/complete")->assertOk();
    }

    public function test_requires_authentication(): void
    {
        $a = $this->firm('A');

        $this->postJson('/api/jobs/'.$this->jobIn($a, null)->id.'/complete')->assertUnauthorized();
    }
}
