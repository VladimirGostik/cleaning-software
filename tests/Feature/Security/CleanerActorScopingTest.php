<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Data\Tenants\InviteData;
use App\Enums\JobStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Policies\ObjectPolicy;
use App\Policies\ScheduledJobPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Own-only actor scoping for /objects and /jobs — see
 * .claude/plans/cleaner-actor-scoping.md (D1-D7 + Security section).
 */
final class CleanerActorScopingTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // jobs.index — own-only list filtering
    // -------------------------------------------------------------------------

    public function test_jobs_index_own_only_shows_only_actors_assigned_jobs(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $ownJobOne = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);
        $ownJobTwo = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
        ]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => null,
        ]);

        $expectedIds = collect([$ownJobOne->id, $ownJobTwo->id])->sort()->values()->all();

        $response = $this->get(route('jobs.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('jobs.data', 2)
            ->where('jobs.data', fn ($jobs) => $jobs->pluck('id')->sort()->values()->all() === $expectedIds),
        );
    }

    public function test_jobs_index_filtering_by_colleague_membership_yields_zero_rows(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
        ]);

        $response = $this->get(route('jobs.index', ['filter' => ['assigned_membership_id' => $colleague->id]]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->has('jobs.data', 0));
    }

    // -------------------------------------------------------------------------
    // jobs.show — record-level visibility
    // -------------------------------------------------------------------------

    public function test_jobs_show_own_job_is_ok(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);

        $this->get(route('jobs.show', $job))->assertOk();
    }

    public function test_jobs_show_colleague_job_is_forbidden(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
        ]);

        $this->get(route('jobs.show', $job))->assertForbidden();
    }

    public function test_jobs_show_unassigned_job_is_forbidden(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => null,
        ]);

        $this->get(route('jobs.show', $job))->assertForbidden();
    }

    public function test_jobs_show_veduca_sees_any_job_in_tenant(): void
    {
        $manager = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $manager->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
        ]);

        $this->get(route('jobs.show', $job))->assertOk();
    }

    // -------------------------------------------------------------------------
    // jobs.show — membershipOptions hardening (D7)
    // -------------------------------------------------------------------------

    public function test_jobs_show_membership_options_empty_for_own_only_actor(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);

        $response = $this->get(route('jobs.show', $job));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->has('membershipOptions', 0));
    }

    public function test_jobs_show_membership_options_non_empty_for_veduca(): void
    {
        $manager = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $manager->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
        ]);

        $response = $this->get(route('jobs.show', $job));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->has('membershipOptions', 1));
    }

    // -------------------------------------------------------------------------
    // jobs.create — objectOptions picker filtered for hand-granted create ability
    // -------------------------------------------------------------------------

    public function test_jobs_create_object_options_filtered_for_actor_without_view_all_objects(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleaner->givePermissionTo(PermissionEnum::CreateSchedule->value);
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $reachableObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $reachableObject->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);

        $response = $this->get(route('jobs.create'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('objectOptions', 1)
            ->where('objectOptions.0.id', $reachableObject->id),
        );
    }

    // -------------------------------------------------------------------------
    // objects.index — own-only list filtering + D3 (any status/date job counts)
    // -------------------------------------------------------------------------

    public function test_objects_index_own_only_shows_only_objects_reachable_via_own_job(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $reachableObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $reachableObject->id,
            'assigned_membership_id' => $cleanerMembership->id,
            'scheduled_date' => now()->subMonth()->toDateString(),
        ]);

        $unreachableObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $unreachableObject->id,
            'assigned_membership_id' => $colleague->id,
        ]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('objects.data', 1)
            ->where('objects.data.0.id', $reachableObject->id)
            ->where('clients', []),
        );
    }

    public function test_objects_index_full_visibility_roles_see_all_objects_and_clients(): void
    {
        $manager = $this->actingAsTenantUser('Vedúca');
        $tenant = Tenant::where('owner_id', $manager->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('objects.data', 2)
            ->has('clients', 1),
        );
    }

    // -------------------------------------------------------------------------
    // objects.show — record-level visibility
    // -------------------------------------------------------------------------

    public function test_objects_show_reachable_via_own_job_is_ok_with_empty_clients(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('clients', []));
    }

    public function test_objects_show_unreachable_object_is_forbidden(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->get(route('objects.show', $object))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Policies — unit-style record checks
    // -------------------------------------------------------------------------

    public function test_object_policy_view_own_only_scoping(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $cleanerMembership = TenantMembership::where('user_id', $cleaner->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $reachableObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $reachableObject->id,
            'assigned_membership_id' => $cleanerMembership->id,
        ]);

        $foreignObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $foreignObject->id,
            'assigned_membership_id' => $colleague->id,
        ]);

        $policy = new ObjectPolicy;

        $this->assertTrue($policy->view($cleaner, $reachableObject));
        $this->assertFalse($policy->view($cleaner, $foreignObject));

        $manager = $this->actingAsTenantUser('Vedúca', $tenant);
        $this->assertTrue($policy->view($manager, $foreignObject));
    }

    public function test_scheduled_job_policy_update_denies_hand_granted_permission_on_foreign_job(): void
    {
        $cleaner = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $cleaner->id)->first();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        // Hand-grant an ability the seeded role does not have — record layer must still deny.
        $cleaner->givePermissionTo(PermissionEnum::EditSchedule->value);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $foreignJob = ScheduledJob::factory()->create([
            'tenant_id' => $tenant->id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $colleague->id,
            'status' => JobStatusEnum::Unassigned,
        ]);

        $this->assertFalse((new ScheduledJobPolicy)->update($cleaner, $foreignJob));
    }

    // -------------------------------------------------------------------------
    // Shared props — Inertia `can` payload reflects the breadth modifiers
    // -------------------------------------------------------------------------

    public function test_can_shared_prop_reflects_view_all_schedule_permission(): void
    {
        $this->actingAsTenantUser('Admin');
        $this->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewAllSchedule', true));

        $this->actingAsTenantUser('Interná upratovačka');
        $this->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewAllSchedule', false));
    }

    // -------------------------------------------------------------------------
    // Invitation — deprecated role name rejected
    // -------------------------------------------------------------------------

    public function test_invite_data_rejects_deprecated_upratovacka_role_name(): void
    {
        $this->expectException(ValidationException::class);

        InviteData::validate(['email' => 'cleaner@example.com', 'role_name' => 'Upratovačka']);
    }
}
