<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Enums\JobTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ScheduledJobControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_jobs_with_filter_options(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->count(2)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('jobs.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Index')
                ->has('jobs.data', 2)
                ->has('filterOptions.objects')
                ->has('filterOptions.memberships'),
        );
    }

    public function test_index_forbidden_without_view_schedule_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);

        $this->get(route('jobs.index'))->assertForbidden();
    }

    public function test_index_filter_options_memberships_empty_for_own_only_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $response = $this->get(route('jobs.index'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Index')
                ->where('filterOptions.memberships', []),
        );
    }

    public function test_index_object_options_exclude_inactive_and_other_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $activeObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => true]);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'is_active' => false]);
        $otherClient = Client::factory()->create(['tenant_id' => $other->id]);
        CleaningObject::factory()->create(['tenant_id' => $other->id, 'client_id' => $otherClient->id]);

        $response = $this->get(route('jobs.index'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Index')
                ->has('filterOptions.objects', 1)
                ->where('filterOptions.objects.0.id', $activeObject->id),
        );
    }

    public function test_index_membership_options_exclude_inactive_memberships(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $response = $this->get(route('jobs.index'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Index')
                ->has(
                    'filterOptions.memberships',
                    fn (AssertableJson $memberships) => $memberships->each(
                        fn (AssertableJson $membership) => $membership->where('is_active', true)->etc(),
                    ),
                ),
        );
    }

    public function test_create_returns_form_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('jobs.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Create')
                ->has('context.objects')
                ->has('context.memberships'),
        );
    }

    public function test_store_creates_job_and_redirects_to_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('jobs.store'), [
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cleaning_jobs', ['cleaning_object_id' => $object->id]);
    }

    public function test_store_forbidden_without_create_schedule_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->post(route('jobs.store'), [
            'cleaning_object_id' => $object->id,
            'type' => JobTypeEnum::OneOff->value,
            'scheduled_date' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }

    public function test_show_returns_job_detail_with_can_map(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('jobs.show', $job));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Schedule/Show')
                ->where('job.id', $job->id)
                ->where('job.can.update', true)
                ->where('job.can.assign', true)
                ->where('job.can.cancel', true),
        );
    }

    public function test_show_cross_tenant_job_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->bindTenant($tenantB);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);
        $jobB = ScheduledJob::factory()->forObject($objectB)->create(['tenant_id' => $tenantB->id]);

        $this->actingAsTenantUser('Admin', $tenantA);

        $this->get(route('jobs.show', $jobB))->assertNotFound();
    }

    public function test_show_own_only_actor_sees_own_job(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->get(route('jobs.show', $job))->assertOk();
    }

    public function test_show_own_only_actor_cannot_see_colleagues_job(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->get(route('jobs.show', $job))->assertForbidden();
    }

    public function test_assign_requires_assign_cleaners_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Sekretárka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('jobs.assign', $job), ['assigned_membership_id' => $membership->id])->assertForbidden();
    }

    public function test_assign_succeeds_for_vedúca(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('jobs.assign', $job), ['assigned_membership_id' => $membership->id])->assertRedirect();

        $this->assertDatabaseHas('cleaning_jobs', ['id' => $job->id, 'assigned_membership_id' => $membership->id]);
    }

    public function test_cancel_completed_job_returns_403(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->completed()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->post(route('jobs.cancel', $job))->assertForbidden();
    }

    public function test_calendar_returns_own_only_rows_for_own_only_actor(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => now()->addDays(2)->toDateString(),
        ]);

        $response = $this->getJson(route('jobs.calendar', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(7)->toDateString(),
        ]));

        $response->assertOk();
        /** @var list<mixed> $items */
        $items = $response->json();
        $this->assertCount(1, $items);
    }

    public function test_calendar_rejects_range_wider_than_62_days(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->getJson(route('jobs.calendar', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(70)->toDateString(),
        ]));

        $response->assertStatus(422);
    }

    public function test_objects_show_carries_work_breakdowns_prop(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('objects.show', $object));

        $response->assertOk();
        $response->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Objects/Show')
                ->has('workBreakdowns'),
        );
    }
}
