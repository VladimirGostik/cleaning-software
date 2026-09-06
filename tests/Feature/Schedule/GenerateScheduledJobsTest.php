<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\TaskFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use App\Services\JobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GenerateScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<string> $with */
    private function reload(WorkBreakdown $breakdown, array $with = ['tasks']): WorkBreakdown
    {
        return WorkBreakdown::with($with)->findOrFail($breakdown->id);
    }

    public function test_generates_expected_job_count_for_weekly_task_in_30_day_window(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(5, $created);
        $this->assertSame(5, ScheduledJob::where('work_breakdown_id', $breakdown->id)->count());
    }

    public function test_regeneration_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $service = app(JobService::class);
        $first = $service->generateForBreakdown($this->reload($breakdown), $period);
        $second = $service->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(5, $first);
        $this->assertSame(0, $second);
        $this->assertSame(5, ScheduledJob::where('work_breakdown_id', $breakdown->id)->count());
    }

    public function test_no_jobs_before_contract_valid_from(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->addDays(60)->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(0, $created);
    }

    public function test_no_jobs_after_contract_end_date(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->subYear()->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(0, $created);
    }

    public function test_one_time_task_generates_single_job(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->oneTime()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(1, $created);
    }

    public function test_breakdown_with_no_tasks_generates_zero_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create(['tenant_id' => $tenant->id]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertSame(0, $created);
    }

    public function test_inactive_object_generates_zero_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);
        $contract = Contract::factory()->forObject($object)->active()->create(['tenant_id' => $tenant->id]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown, ['tasks', 'cleaningObject']), $period);

        $this->assertSame(0, $created);
    }

    public function test_trashed_object_generates_zero_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create(['tenant_id' => $tenant->id]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->frequency(TaskFrequencyEnum::Weekly1x)->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);
        $object->delete();

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown, ['tasks', 'cleaningObject']), $period);

        $this->assertSame(0, $created);
    }

    public function test_active_contract_and_object_proceeds_with_generation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create([
            'tenant_id' => $tenant->id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);
        WorkBreakdownTask::factory()->oneTime()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = now()->toPeriod(now()->addDays(29));
        $created = app(JobService::class)->generateForBreakdown($this->reload($breakdown), $period);

        $this->assertGreaterThan(0, $created);
    }
}
