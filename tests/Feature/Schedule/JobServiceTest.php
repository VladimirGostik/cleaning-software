<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobCalendarFilterData;
use App\Data\Schedule\JobStoreData;
use App\Data\Schedule\JobUpdateData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\JobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class JobServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_unassigned_job_when_no_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = app(JobService::class)->create(new JobStoreData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::OneOff,
            scheduled_date: now()->addDay()->toDateString(),
        ), $actor);

        $this->assertSame(JobStatusEnum::Unassigned, $job->status);
        $this->assertNull($job->assigned_membership_id);
    }

    public function test_create_creates_planned_job_when_assignee_given_by_authorized_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $job = app(JobService::class)->create(new JobStoreData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::OneOff,
            scheduled_date: now()->addDay()->toDateString(),
            assigned_membership_id: $membership->id,
        ), $actor);

        $this->assertSame(JobStatusEnum::Planned, $job->status);
        $this->assertSame($membership->id, $job->assigned_membership_id);
    }

    public function test_create_throws_when_actor_lacks_assign_cleaners_and_supplies_assignee(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Sekretárka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->create(new JobStoreData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::OneOff,
            scheduled_date: now()->addDay()->toDateString(),
            assigned_membership_id: $membership->id,
        ), $actor);
    }

    public function test_create_throws_when_object_not_visible_to_own_only_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->create(new JobStoreData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::OneOff,
            scheduled_date: now()->addDay()->toDateString(),
        ), $actor);
    }

    public function test_update_updates_fields_but_never_touches_assignment_or_status(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $job = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $updated = app(JobService::class)->update($job, new JobUpdateData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::Special,
            scheduled_date: now()->addDays(2)->toDateString(),
            note: 'Updated note',
        ), $actor);

        $this->assertSame(JobTypeEnum::Special, $updated->type);
        $this->assertSame('Updated note', $updated->note);
        $this->assertSame(JobStatusEnum::Planned, $updated->status);
        $this->assertSame($membership->id, $updated->assigned_membership_id);
    }

    public function test_update_throws_when_job_not_editable(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->completed()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->update($job, new JobUpdateData(
            cleaning_object_id: $object->id,
            type: JobTypeEnum::OneOff,
            scheduled_date: now()->toDateString(),
        ), $actor);
    }

    public function test_assign_sets_planned_status_and_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $assigned = app(JobService::class)->assign($job, new JobAssignData($membership->id));

        $this->assertSame(JobStatusEnum::Planned, $assigned->status);
        $this->assertSame($membership->id, $assigned->assigned_membership_id);
    }

    public function test_assign_to_null_unassigns_and_sets_unassigned_status(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $job = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $assigned = app(JobService::class)->assign($job, new JobAssignData(null));

        $this->assertSame(JobStatusEnum::Unassigned, $assigned->status);
        $this->assertNull($assigned->assigned_membership_id);
    }

    public function test_assign_throws_when_job_cannot_be_assigned(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->cancelled()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->assign($job, new JobAssignData(null));
    }

    public function test_cancel_sets_cancelled_status_and_timestamp(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->planned()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $cancelled = app(JobService::class)->cancel($job);

        $this->assertSame(JobStatusEnum::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancel_throws_when_job_cannot_be_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->completed()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->cancel($job);
    }

    public function test_complete_transitions_in_progress_to_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->inProgress()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $completed = app(JobService::class)->complete($job);

        $this->assertSame(JobStatusEnum::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_complete_throws_on_invalid_transition(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->complete($job);
    }

    public function test_unapprove_transitions_in_progress_to_unapproved(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->inProgress()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $unapproved = app(JobService::class)->unapprove($job);

        $this->assertSame(JobStatusEnum::Unapproved, $unapproved->status);
    }

    public function test_unapprove_throws_on_invalid_transition(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(JobService::class)->unapprove($job);
    }

    public function test_unassign_future_for_membership_skips_past_and_non_planned_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $futurePlanned = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ]);
        $pastPlanned = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->subDays(3)->toDateString(),
        ]);
        $futureCompleted = ScheduledJob::factory()->assignedTo($membership)->completed()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);

        $count = app(JobService::class)->unassignFutureForMembership($membership);

        $futurePlanned->refresh();
        $pastPlanned->refresh();
        $futureCompleted->refresh();

        $this->assertSame(1, $count);
        $this->assertSame(JobStatusEnum::Unassigned, $futurePlanned->status);
        $this->assertNull($futurePlanned->assigned_membership_id);
        $this->assertSame($membership->id, $pastPlanned->assigned_membership_id);
        $this->assertSame($membership->id, $futureCompleted->assigned_membership_id);
    }

    public function test_paginate_with_view_all_schedule_returns_all_tenant_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->count(3)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $paginator = app(JobService::class)->paginate(Request::create('/jobs'), $actor);

        $this->assertSame(3, $paginator->total());
    }

    public function test_paginate_without_view_all_schedule_returns_only_own_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();

        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);
        ScheduledJob::factory()->count(2)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $paginator = app(JobService::class)->paginate(Request::create('/jobs'), $actor);

        $this->assertSame(1, $paginator->total());
    }

    public function test_calendar_respects_visibility_and_date_range(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $inRangeOwn = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(40)->toDateString(),
        ]);

        $items = app(JobService::class)->calendar(new JobCalendarFilterData(
            from: now()->toDateString(),
            to: now()->addDays(7)->toDateString(),
        ), $actor);

        $this->assertCount(1, $items);
        $this->assertSame($inRangeOwn->id, $items->sole()->id);
    }
}
