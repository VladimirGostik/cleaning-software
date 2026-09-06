<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

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

    private ScheduledJobPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ScheduledJobPolicy;
    }

    public function test_admin_can_do_everything_on_own_object(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $job));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $job));
        $this->assertTrue($this->policy->assign($user, $job));
        $this->assertTrue($this->policy->cancel($user, $job));
    }

    public function test_own_only_actor_sees_only_own_assigned_job(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $ownJob = ScheduledJob::factory()->assignedTo($membership)->forObject($object)->create(['tenant_id' => $tenant->id]);
        $otherJob = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->view($user, $ownJob));
        $this->assertFalse($this->policy->view($user, $otherJob));
    }

    public function test_own_only_actor_cannot_create_or_assign(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->assign($user, $job));
    }

    public function test_customer_sees_no_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Zákazník', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $job));
    }

    public function test_state_guards_block_update_assign_cancel_on_terminal_job(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->completed()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->update($user, $job));
        $this->assertFalse($this->policy->assign($user, $job));
        $this->assertFalse($this->policy->cancel($user, $job));
    }

    public function test_view_schedule_without_permission_denies_view_any(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_assign_cleaners_permission_gates_assign_regardless_of_edit_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Vedúca', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $job = ScheduledJob::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->assign($user, $job));
    }
}
