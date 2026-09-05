<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Policies\ScheduledJobPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduledJobPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makePlanningJob(string $tenantId, string $objectId, JobStatusEnum $status = JobStatusEnum::Unassigned): ScheduledJob
    {
        return ScheduledJob::factory()->create([
            'tenant_id' => $tenantId,
            'cleaning_object_id' => $objectId,
            'status' => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Admin — full access
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_view_any(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->assertTrue((new ScheduledJobPolicy)->viewAny($user));
    }

    public function test_vlastnik_can_create(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $this->assertTrue((new ScheduledJobPolicy)->create($user));
    }

    public function test_vlastnik_can_update_editable_job(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Unassigned);

        $this->assertTrue((new ScheduledJobPolicy)->update($user, $job));
    }

    public function test_vlastnik_cannot_update_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Completed);

        $this->assertFalse((new ScheduledJobPolicy)->update($user, $job));
    }

    public function test_vlastnik_can_cancel_planned_job(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Planned);

        $this->assertTrue((new ScheduledJobPolicy)->cancel($user, $job));
    }

    public function test_vlastnik_cannot_cancel_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Completed);

        $this->assertFalse((new ScheduledJobPolicy)->cancel($user, $job));
    }

    // -------------------------------------------------------------------------
    // Vedúca — has view + create + edit schedule + assign cleaners
    // -------------------------------------------------------------------------

    public function test_veduci_can_view_any(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');

        $this->assertTrue((new ScheduledJobPolicy)->viewAny($user));
    }

    public function test_veduci_can_assign_unassigned_job(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Unassigned);

        $this->assertTrue((new ScheduledJobPolicy)->assign($user, $job));
    }

    public function test_veduci_cannot_assign_completed_job(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Completed);

        $this->assertFalse((new ScheduledJobPolicy)->assign($user, $job));
    }

    // -------------------------------------------------------------------------
    // Interná upratovačka — can view schedule, but cannot create/edit/assign
    // -------------------------------------------------------------------------

    public function test_upratovacka_can_view_any(): void
    {
        // Interná upratovačka has ViewSchedule permission per RoleTemplatesSeeder.
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->assertTrue((new ScheduledJobPolicy)->viewAny($user));
    }

    public function test_upratovacka_cannot_create(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $this->assertFalse((new ScheduledJobPolicy)->create($user));
    }

    public function test_upratovacka_cannot_assign(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Unassigned);

        $this->assertFalse((new ScheduledJobPolicy)->assign($user, $job));
    }

    public function test_upratovacka_cannot_update(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Unassigned);

        $this->assertFalse((new ScheduledJobPolicy)->update($user, $job));
    }

    // -------------------------------------------------------------------------
    // Own-only visibility (view) — .claude/plans/cleaner-actor-scoping.md
    // -------------------------------------------------------------------------

    public function test_upratovacka_can_view_own_assigned_job(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $membership = TenantMembership::where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $membership->id,
            'status' => JobStatusEnum::Planned,
        ]);

        $this->assertTrue((new ScheduledJobPolicy)->view($user, $job));
    }

    public function test_upratovacka_cannot_view_foreign_assigned_job(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
            'status' => JobStatusEnum::Planned,
        ]);

        $this->assertFalse((new ScheduledJobPolicy)->view($user, $job));
    }

    public function test_upratovacka_cannot_view_unassigned_job(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = $this->makePlanningJob($tenant->id, $object->id, JobStatusEnum::Unassigned);

        $this->assertFalse((new ScheduledJobPolicy)->view($user, $job));
    }

    public function test_veduci_can_view_colleague_assigned_job(): void
    {
        $user = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
            'status' => JobStatusEnum::Planned,
        ]);

        $this->assertTrue((new ScheduledJobPolicy)->view($user, $job));
    }
}
