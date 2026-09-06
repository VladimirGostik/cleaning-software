<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Tenant;
use App\Policies\ContractPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContractPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ContractPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ContractPolicy;
    }

    public function test_admin_can_do_everything(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);
        $active = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $draft));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $draft));
        $this->assertTrue($this->policy->sign($user, $draft));
        $this->assertTrue($this->policy->terminate($user, $active));
        $this->assertTrue($this->policy->delete($user, $draft));
        $this->assertTrue($this->policy->downloadPdf($user, $draft));
    }

    public function test_secretary_can_edit_sign_and_terminate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Sekretárka', $tenant);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);
        $active = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $draft));
        $this->assertTrue($this->policy->sign($user, $draft));
        $this->assertTrue($this->policy->terminate($user, $active));
        $this->assertTrue($this->policy->delete($user, $draft));
    }

    public function test_state_guards_block_actions_on_wrong_status(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $active = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->sign($user, $active));
        $this->assertFalse($this->policy->update($user, $active));
        $this->assertFalse($this->policy->delete($user, $active));
        $this->assertFalse($this->policy->terminate($user, $draft));
    }

    public function test_accountant_is_view_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $draft));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $draft));
        $this->assertFalse($this->policy->sign($user, $draft));
        $this->assertFalse($this->policy->delete($user, $draft));
    }

    public function test_cleaner_has_no_contract_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $draft));
        $this->assertFalse($this->policy->create($user));
    }
}
