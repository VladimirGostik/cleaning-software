<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ScheduledJobControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Feature gate — requires schedule feature (Pro+)
    // -------------------------------------------------------------------------

    public function test_index_requires_schedule_feature(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        // Default Free plan — no schedule feature

        $this->get(route('jobs.index'))->assertForbidden();
    }

    public function test_index_accessible_on_pro_plan(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->get(route('jobs.index'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // index — permission gate
    // -------------------------------------------------------------------------

    public function test_index_forbidden_for_sekretarka(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $this->get(route('jobs.index'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create — props
    // -------------------------------------------------------------------------

    public function test_create_passes_object_options_and_membership_options(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        // Act
        $response = $this->get(route('jobs.create'));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Schedule/Create')
            ->has('objectOptions', 1)
            ->has('membershipOptions', 1), // the user's own membership from actingAsTenantUser
        );
    }

    public function test_create_object_options_excludes_inactive_objects(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);

        // Act
        $response = $this->get(route('jobs.create'));

        // Assert — only 1 active object visible
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('objectOptions', 1),
        );
    }

    public function test_create_object_options_excludes_other_tenant_objects(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Own tenant object
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        // Foreign tenant object
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id, 'is_active' => true]);

        // Act
        $response = $this->get(route('jobs.create'));

        // Assert — only own tenant's 1 object visible
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('objectOptions', 1),
        );
    }

    // -------------------------------------------------------------------------
    // store — happy path
    // -------------------------------------------------------------------------

    public function test_store_creates_job_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->post(route('jobs.store'), [
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('cleaning_jobs', [
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'status' => JobStatusEnum::Unassigned->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // store — tenant isolation (cross-tenant object rejected)
    // -------------------------------------------------------------------------

    public function test_store_rejects_cross_tenant_cleaning_object(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignObject = CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $this->post(route('jobs.store'), [
            'cleaning_object_id' => $foreignObject->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ])->assertSessionHasErrors('cleaning_object_id');
    }

    // -------------------------------------------------------------------------
    // edit — props
    // -------------------------------------------------------------------------

    public function test_edit_passes_object_options_and_membership_options(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        // Act
        $response = $this->get(route('jobs.edit', $job));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Schedule/Edit')
            ->has('objectOptions', 1)
            ->has('membershipOptions', 1),
        );
    }

    public function test_edit_membership_options_excludes_inactive_memberships(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Extra inactive membership in same tenant
        $extraUser = User::factory()->create(['is_active' => true]);
        TenantMembership::factory()->inactive()->create(['tenant_id' => $tenant->id, 'user_id' => $extraUser->id]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        $job = ScheduledJob::factory()->create(['tenant_id' => $tenant->id, 'cleaning_object_id' => $object->id]);

        // Act
        $response = $this->get(route('jobs.edit', $job));

        // Assert — only 1 active membership (the actingAs user's), inactive excluded
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('membershipOptions', 1),
        );
    }

    // -------------------------------------------------------------------------
    // assign — Vedúca can assign an unassigned job
    // -------------------------------------------------------------------------

    public function test_veduci_can_assign_job(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
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

        $this->post(route('jobs.assign', $job), [
            'assigned_membership_id' => $membership->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $job->id,
            'assigned_membership_id' => $membership->id,
            'status' => JobStatusEnum::Planned->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // assign — Upratovačka cannot assign
    // -------------------------------------------------------------------------

    public function test_upratovacka_cannot_assign_job(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
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

        $this->post(route('jobs.assign', $job), [
            'assigned_membership_id' => $membership->id,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // cancel — happy path
    // -------------------------------------------------------------------------

    public function test_cancel_sets_job_cancelled_and_redirects(): void
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

        $this->post(route('jobs.cancel', $job))->assertRedirect();

        $this->assertDatabaseHas('cleaning_jobs', [
            'id' => $job->id,
            'status' => JobStatusEnum::Cancelled->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // cancel — Completed job returns validation error
    // -------------------------------------------------------------------------

    public function test_cancel_completed_job_returns_validation_error(): void
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

        // Policy denies cancel on completed job → 403
        $this->post(route('jobs.cancel', $job))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // show — work breakdown props
    // -------------------------------------------------------------------------

    public function test_show_work_breakdown_is_null_when_job_not_linked_to_breakdown(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'work_breakdown_id' => null,
        ]);

        // Act
        $response = $this->get(route('jobs.show', $job));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Schedule/Show')
            ->where('workBreakdown', null),
        );
    }

    public function test_show_work_breakdown_is_present_when_job_linked_to_breakdown(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $breakdown = WorkBreakdown::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);
        WorkBreakdownTask::factory()->create([
            'tenant_id' => $tenant->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'work_breakdown_id' => $breakdown->id,
        ]);

        // Act
        $response = $this->get(route('jobs.show', $job));

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Schedule/Show')
            ->where('workBreakdown.id', $breakdown->id)
            ->has('workBreakdown.tasks', 1)
            ->has('membershipOptions'),
        );
    }

    public function test_show_passes_membership_options(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        // Act
        $response = $this->get(route('jobs.show', $job));

        // Assert — 1 membership (the acting user's)
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('membershipOptions', 1),
        );
    }

    // -------------------------------------------------------------------------
    // show — tenant isolation (cannot see other tenant's job)
    // -------------------------------------------------------------------------

    public function test_show_returns_404_for_other_tenant_job(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Create job for a completely different tenant
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherObject = CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $foreignJob = ScheduledJob::factory()->create([
            'tenant_id' => $otherTenant->id,
            'cleaning_object_id' => $otherObject->id,
        ]);

        $this->get(route('jobs.show', $foreignJob))->assertNotFound();
    }
}
