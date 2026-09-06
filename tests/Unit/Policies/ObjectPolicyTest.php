<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Policies\ObjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ObjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ObjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ObjectPolicy;
    }

    public function test_admin_can_do_everything(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $object));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $object));
        $this->assertTrue($this->policy->delete($user, $object));
    }

    public function test_secretary_can_do_everything(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Sekretárka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $object));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $object));
        $this->assertTrue($this->policy->delete($user, $object));
    }

    public function test_vedouca_can_view_but_not_update_or_delete(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Vedúca', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $object));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $object));
        $this->assertFalse($this->policy->delete($user, $object));
    }

    /** D2 fail-closed: own-only actor sees `viewAny` (permission check) but not any single object (no jobs yet). */
    public function test_cleaner_sees_view_any_but_not_individual_object(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $object));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $object));
        $this->assertFalse($this->policy->delete($user, $object));
    }
}
