<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Client;
use App\Models\Tenant;
use App\Policies\ClientPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ClientPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ClientPolicy;
    }

    public function test_owner_can_view_any(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Assert
        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $client));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $client));
        $this->assertTrue($this->policy->delete($user, $client));
    }

    public function test_accountant_can_only_view(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Assert
        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $client));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $client));
        $this->assertFalse($this->policy->delete($user, $client));
    }

    public function test_secretary_can_view_create_update_delete(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Sekretárka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Assert
        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $client));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $client));
        $this->assertTrue($this->policy->delete($user, $client));
    }

    public function test_cleaner_cannot_view_clients(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Assert
        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $client));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $client));
        $this->assertFalse($this->policy->delete($user, $client));
    }
}
