<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use App\Services\JobService;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class GenerateScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeBreakdown(Tenant $tenant, CleaningObject $object, ?Contract $contract = null): WorkBreakdown
    {
        return WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'contract_id' => $contract?->id,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Weekly recurrence creates expected count in horizon
    // -------------------------------------------------------------------------

    public function test_weekly_task_generates_correct_count_in_30_day_period(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $breakdown = $this->makeBreakdown($tenant, $object);

        WorkBreakdownTask::factory()->weekly()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
            'position' => 0,
        ]);

        $start = Carbon::today();
        $end = Carbon::today()->addDays(29);
        $period = CarbonPeriod::create($start, '1 day', $end);

        $count = app(JobService::class)->generateForBreakdown($breakdown, $period);

        // Weekly = every 7 days. 30-day window → 0,7,14,21,28 = 5 jobs
        $this->assertSame(5, $count);
        $this->assertDatabaseCount('cleaning_jobs', 5);
    }

    // -------------------------------------------------------------------------
    // Idempotency — re-running does not create duplicates
    // -------------------------------------------------------------------------

    public function test_generate_is_idempotent_no_duplicates_on_re_run(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $breakdown = $this->makeBreakdown($tenant, $object);

        WorkBreakdownTask::factory()->weekly()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = CarbonPeriod::create(Carbon::today(), '1 day', Carbon::today()->addDays(13));
        $service = app(JobService::class);

        $firstCount = $service->generateForBreakdown($breakdown, $period);
        $secondCount = $service->generateForBreakdown($breakdown, $period);

        $this->assertSame(0, $secondCount);
        $this->assertDatabaseCount('cleaning_jobs', $firstCount);
    }

    // -------------------------------------------------------------------------
    // Contract validity window respected — no jobs outside valid_from/end_date
    // -------------------------------------------------------------------------

    public function test_jobs_not_generated_before_contract_valid_from(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Active,
            'term_type' => ContractTermTypeEnum::Fixed,
            // contract starts in 5 days — nothing should be generated before that
            'valid_from' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
        ]);

        $breakdown = $this->makeBreakdown($tenant, $object, $contract);

        WorkBreakdownTask::factory()->weekly()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        // period starts today (before valid_from)
        $period = CarbonPeriod::create(Carbon::today(), '1 day', Carbon::today()->addDays(9));

        $count = app(JobService::class)->generateForBreakdown($breakdown, $period);

        // valid_from is day 5, end_date is day 20. In days 0-9, only day 5 matches weekly
        // reference = valid_from (day 5), aligned days in period: day 5 (diff=0 → 0%7=0 ✓), day 12 outside period
        $this->assertSame(1, $count);

        $jobs = ScheduledJob::all();
        foreach ($jobs as $job) {
            $this->assertGreaterThanOrEqual(
                now()->addDays(5)->startOfDay()->timestamp,
                $job->scheduled_date->startOfDay()->timestamp,
                'No job should be scheduled before contract valid_from',
            );
        }
    }

    public function test_jobs_not_generated_after_contract_end_date(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Active,
            'term_type' => ContractTermTypeEnum::Fixed,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(), // contract ends in 6 days
        ]);

        $breakdown = $this->makeBreakdown($tenant, $object, $contract);

        WorkBreakdownTask::factory()->weekly()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        // period extends past contract end_date
        $period = CarbonPeriod::create(Carbon::today(), '1 day', Carbon::today()->addDays(13));

        $count = app(JobService::class)->generateForBreakdown($breakdown, $period);

        // Weekly from today: day 0 (ok), day 7 (past end_date day 6) → only 1 job
        $this->assertSame(1, $count);

        foreach (ScheduledJob::all() as $job) {
            $this->assertLessThanOrEqual(
                now()->addDays(6)->endOfDay()->timestamp,
                $job->scheduled_date->endOfDay()->timestamp,
                'No job should be scheduled after contract end_date',
            );
        }
    }

    // -------------------------------------------------------------------------
    // OneTime task creates exactly one job on period start
    // -------------------------------------------------------------------------

    public function test_one_time_task_generates_single_job_on_period_start(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $breakdown = $this->makeBreakdown($tenant, $object);

        WorkBreakdownTask::factory()->oneTime()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $period = CarbonPeriod::create(Carbon::today(), '1 day', Carbon::today()->addDays(29));

        $count = app(JobService::class)->generateForBreakdown($breakdown, $period);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('cleaning_jobs', [
            'scheduled_date' => Carbon::today()->toDateString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Inactive breakdown with no tasks returns 0
    // -------------------------------------------------------------------------

    public function test_generate_returns_zero_for_breakdown_with_no_tasks(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $breakdown = WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'is_active' => true,
        ]);
        // no tasks created

        $period = CarbonPeriod::create(Carbon::today(), '1 day', Carbon::today()->addDays(6));

        $count = app(JobService::class)->generateForBreakdown($breakdown, $period);

        $this->assertSame(0, $count);
        $this->assertDatabaseCount('cleaning_jobs', 0);
    }
}
