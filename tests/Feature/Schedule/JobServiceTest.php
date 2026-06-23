<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobUpsertData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\JobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class JobServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create — happy path (unassigned)
    // -------------------------------------------------------------------------

    public function test_create_manual_job_without_assignee_is_unassigned(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $data = JobUpsertData::from([
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ]);

        $job = app(JobService::class)->create($data);

        $this->assertSame(JobStatusEnum::Unassigned, $job->status);
        $this->assertNull($job->assigned_membership_id);
        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $job->id,
            'status' => JobStatusEnum::Unassigned->value,
            'cleaning_object_id' => $object->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // create — with assignee → Planned
    // -------------------------------------------------------------------------

    public function test_create_job_with_assignee_is_planned(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $data = JobUpsertData::from([
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'assigned_membership_id' => $membership->id,
        ]);

        $job = app(JobService::class)->create($data);

        $this->assertSame(JobStatusEnum::Planned, $job->status);
        $this->assertSame($membership->id, $job->assigned_membership_id);
    }

    // -------------------------------------------------------------------------
    // assign — changes membership + status
    // -------------------------------------------------------------------------

    public function test_assign_sets_membership_and_transitions_to_planned(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'status' => JobStatusEnum::Unassigned,
        ]);

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $data = JobAssignData::from(['assigned_membership_id' => $membership->id]);
        $updated = app(JobService::class)->assign($job, $data);

        $this->assertSame(JobStatusEnum::Planned, $updated->status);
        $this->assertSame($membership->id, $updated->assigned_membership_id);
    }

    // -------------------------------------------------------------------------
    // assign — Completed job cannot be assigned
    // -------------------------------------------------------------------------

    public function test_assign_throws_on_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->completed()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $this->expectException(ValidationException::class);

        app(JobService::class)->assign($job, JobAssignData::from(['assigned_membership_id' => $membership->id]));
    }

    // -------------------------------------------------------------------------
    // cancel — Unassigned job can be cancelled
    // -------------------------------------------------------------------------

    public function test_cancel_unassigned_job_sets_cancelled_status_and_timestamp(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'status' => JobStatusEnum::Unassigned,
        ]);

        $cancelled = app(JobService::class)->cancel($job);

        $this->assertSame(JobStatusEnum::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    // -------------------------------------------------------------------------
    // cancel — Completed job cannot be cancelled
    // -------------------------------------------------------------------------

    public function test_cancel_throws_on_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->completed()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->cancel($job);
    }

    // -------------------------------------------------------------------------
    // update — Completed job is not editable
    // -------------------------------------------------------------------------

    public function test_update_throws_on_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->completed()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        $data = JobUpsertData::from([
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->update($job, $data);
    }

    // -------------------------------------------------------------------------
    // unassignFutureForMembership — clears future jobs on deactivation
    // -------------------------------------------------------------------------

    public function test_unassign_future_for_membership_clears_planned_future_jobs(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        // Future job for this membership — should be unassigned
        $futureJob = ScheduledJob::factory()->planned()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $membership->id,
            'scheduled_date' => now()->addDays(5)->toDateString(),
        ]);

        // Past job for this membership — should NOT be touched
        $pastJob = ScheduledJob::factory()->planned()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $membership->id,
            'scheduled_date' => now()->subDays(3)->toDateString(),
        ]);

        $count = app(JobService::class)->unassignFutureForMembership($membership);

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $futureJob->id,
            'assigned_membership_id' => null,
            'status' => JobStatusEnum::Unassigned->value,
        ]);

        // Past job unchanged
        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $pastJob->id,
            'assigned_membership_id' => $membership->id,
            'status' => JobStatusEnum::Planned->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // unassignFutureForMembership — completed jobs are not touched
    // -------------------------------------------------------------------------

    public function test_unassign_future_skips_completed_jobs(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $completedJob = ScheduledJob::factory()->completed()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $membership->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);

        $count = app(JobService::class)->unassignFutureForMembership($membership);

        $this->assertSame(0, $count);

        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $completedJob->id,
            'assigned_membership_id' => $membership->id,
            'status' => JobStatusEnum::Completed->value,
        ]);
    }
}
