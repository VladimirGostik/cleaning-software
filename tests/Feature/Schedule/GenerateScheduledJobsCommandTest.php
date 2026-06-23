<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\SubscriptionPlanEnum;
use App\Jobs\GenerateScheduledJobsJob;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\WorkBreakdown;
use App\Scopes\TenantScope;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class GenerateScheduledJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Dispatches one job per active breakdown with active contract
    // -------------------------------------------------------------------------

    public function test_command_dispatches_job_per_active_breakdown_with_active_contract(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $activeContract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
        ]);

        $activeBreakdown = WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'contract_id' => $activeContract->id,
            'is_active' => true,
        ]);

        $this->artisan('app:generate-scheduled-jobs')->assertExitCode(0);

        Queue::assertPushed(GenerateScheduledJobsJob::class, function (GenerateScheduledJobsJob $job) use ($activeBreakdown) {
            return $job->workBreakdownId === $activeBreakdown->id;
        });
    }

    // -------------------------------------------------------------------------
    // Does NOT dispatch for inactive breakdown
    // -------------------------------------------------------------------------

    public function test_command_does_not_dispatch_for_inactive_breakdown(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $activeContract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
        ]);

        WorkBreakdown::factory()->inactive()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'contract_id' => $activeContract->id,
        ]);

        $this->artisan('app:generate-scheduled-jobs')->assertExitCode(0);

        Queue::assertNotPushed(GenerateScheduledJobsJob::class);
    }

    // -------------------------------------------------------------------------
    // Does NOT dispatch for breakdown with non-active contract
    // -------------------------------------------------------------------------

    public function test_command_does_not_dispatch_for_breakdown_with_draft_contract(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $draftContract = Contract::factory()->draft()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
        ]);

        WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'contract_id' => $draftContract->id,
            'is_active' => true,
        ]);

        $this->artisan('app:generate-scheduled-jobs')->assertExitCode(0);

        Queue::assertNotPushed(GenerateScheduledJobsJob::class);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant: bypasses TenantScope (dispatches for any tenant)
    // -------------------------------------------------------------------------

    public function test_command_bypasses_tenant_scope_and_processes_all_tenants(): void
    {
        Queue::fake();

        // Tenant A
        $userA = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($userA, SubscriptionPlanEnum::Pro);
        $tenantA = Tenant::where('owner_id', $userA->id)->first();
        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $objectA = CleaningObject::factory()->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        $contractA = Contract::factory()->active()->create([
            'tenant_id' => $tenantA->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $objectA->id,
        ]);
        $breakdownA = WorkBreakdown::factory()->create([
            'tenant_id' => $tenantA->id,
            'cleaning_object_id' => $objectA->id,
            'contract_id' => $contractA->id,
            'is_active' => true,
        ]);

        // Tenant B (separate tenant, different owner)
        $tenantB = Tenant::factory()->create();
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);
        $contractB = Contract::factory()->active()->create([
            'tenant_id' => $tenantB->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $objectB->id,
        ]);
        $breakdownB = WorkBreakdown::factory()->create([
            'tenant_id' => $tenantB->id,
            'cleaning_object_id' => $objectB->id,
            'contract_id' => $contractB->id,
            'is_active' => true,
        ]);

        $this->artisan('app:generate-scheduled-jobs')->assertExitCode(0);

        Queue::assertPushed(GenerateScheduledJobsJob::class, 2);
    }

    // -------------------------------------------------------------------------
    // Command is scheduled daily in console.php
    // -------------------------------------------------------------------------

    public function test_command_is_scheduled_daily(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $found = collect($schedule->events())->first(
            fn ($e) => str_contains($e->command ?? '', 'app:generate-scheduled-jobs'),
        );

        $this->assertNotNull($found, 'app:generate-scheduled-jobs is not registered in the schedule');
        $this->assertSame('0 0 * * *', $found->expression, 'Expected daily cron expression');
    }
}
