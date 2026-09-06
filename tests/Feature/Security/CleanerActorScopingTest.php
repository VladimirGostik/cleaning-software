<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Policies\ScheduledJobPolicy;
use App\Services\JobService;
use App\Services\ObjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Cross-cutting invariant pins for D3 (own-only reachability via ANY assigned job) across
 * `ScheduledJob` and `CleaningObject`. Complements per-module policy/controller tests.
 */
final class CleanerActorScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_only_actor_with_no_assigned_jobs_sees_zero_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $options = app(ObjectService::class)->optionsVisibleTo($actor);

        $this->assertSame([], $options);
    }

    public function test_own_only_actor_with_no_assigned_jobs_sees_zero_schedule_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->count(3)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $paginator = app(JobService::class)->paginate(Request::create('/jobs'), $actor);

        $this->assertSame(0, $paginator->total());
    }

    public function test_own_only_actor_reaches_object_via_any_job_regardless_of_status(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->assignedTo($membership)->cancelled()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($object->isVisibleTo($actor));
    }

    public function test_own_only_actor_reaches_object_via_a_past_job(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->assignedTo($membership)->completed()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->subMonth()->toDateString(),
        ]);

        $this->assertTrue($object->isVisibleTo($actor));
    }

    public function test_trashed_job_does_not_grant_object_visibility(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);
        $job->delete();

        $this->assertFalse($object->isVisibleTo($actor));
    }

    public function test_own_only_actor_does_not_reach_object_assigned_only_to_a_colleague(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $ownObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $colleagueObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        ScheduledJob::factory()->assignedTo($membership)->forObject($ownObject)->create(['tenant_id' => $tenant->id]);
        ScheduledJob::factory()->assignedTo($colleague)->forObject($colleagueObject)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($ownObject->isVisibleTo($actor));
        $this->assertFalse($colleagueObject->isVisibleTo($actor));
    }

    public function test_customer_role_sees_zero_objects_and_zero_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Zákazník', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertSame([], app(ObjectService::class)->optionsVisibleTo($actor));
        $this->assertSame(0, app(JobService::class)->paginate(Request::create('/jobs'), $actor)->total());
    }

    public function test_hand_granted_edit_schedule_on_own_only_actor_still_cannot_touch_colleagues_job(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $actor->givePermissionTo('edit schedule');
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $colleagueJob = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertFalse((new ScheduledJobPolicy)->update($actor, $colleagueJob));
    }

    public function test_can_shared_prop_reflects_view_all_schedule_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewAllSchedule', true));
    }

    public function test_can_shared_prop_reflects_view_all_objects_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewAllObjects', false));
    }
}
