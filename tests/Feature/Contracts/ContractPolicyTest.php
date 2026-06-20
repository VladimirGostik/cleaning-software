<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContractPolicyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Vlastník — full contract access
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_list_contracts(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.index'));

        $response->assertOk();
    }

    public function test_vlastnik_can_view_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->get(route('contracts.show', $contract));

        $response->assertOk();
    }

    public function test_vlastnik_can_access_create_contract_page(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.create'));

        $response->assertOk();
    }

    public function test_vlastnik_can_edit_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->get(route('contracts.edit', $contract));

        $response->assertOk();
    }

    public function test_vlastnik_cannot_edit_active_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Active);

        $response = $this->get(route('contracts.edit', $contract));

        $response->assertForbidden();
    }

    public function test_vlastnik_can_sign_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.sign', $contract));

        $response->assertRedirect();
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);
    }

    public function test_vlastnik_cannot_sign_active_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.sign', $contract));

        $response->assertForbidden();
    }

    public function test_vlastnik_can_terminate_active_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
            'termination_reason' => 'Mutual agreement',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Terminated->value,
        ]);
    }

    public function test_vlastnik_cannot_terminate_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_vlastnik_can_delete_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->delete(route('contracts.destroy', $contract));

        $response->assertRedirect(route('contracts.index'));
        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // Sekretárka — view/create/delete only (no edit/sign/terminate)
    // -------------------------------------------------------------------------

    public function test_sekretarka_can_list_contracts(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.index'));

        $response->assertOk();
    }

    public function test_sekretarka_can_view_contract(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->get(route('contracts.show', $contract));

        $response->assertOk();
    }

    public function test_sekretarka_can_access_create_contract_page(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.create'));

        $response->assertOk();
    }

    public function test_sekretarka_cannot_edit_contract(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->get(route('contracts.edit', $contract));

        $response->assertForbidden();
    }

    public function test_sekretarka_cannot_sign_contract(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.sign', $contract));

        $response->assertForbidden();
    }

    public function test_sekretarka_cannot_terminate_contract(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_sekretarka_can_delete_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->delete(route('contracts.destroy', $contract));

        $response->assertRedirect(route('contracts.index'));
        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // Účtovníčka — view only
    // -------------------------------------------------------------------------

    public function test_uctovnicka_can_list_contracts(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.index'));

        $response->assertOk();
    }

    public function test_uctovnicka_cannot_access_create_contract_page(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contracts.create'));

        $response->assertForbidden();
    }

    public function test_uctovnicka_cannot_sign_contract(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.sign', $contract));

        $response->assertForbidden();
    }

    public function test_uctovnicka_cannot_terminate_contract(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_uctovnicka_cannot_delete_contract(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeContract($tenant, ContractStatusEnum::Draft);

        $response = $this->delete(route('contracts.destroy', $contract));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Cross-tenant isolation
    // -------------------------------------------------------------------------

    public function test_contract_from_other_tenant_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $client->id]);
        $foreignContract = Contract::factory()->create([
            'tenant_id' => $otherTenant->id,
            'contractable_id' => $object->id,
        ]);

        $response = $this->get(route('contracts.show', $foreignContract));

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_redirected_from_contracts_index(): void
    {
        $response = $this->get(route('contracts.index'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeContract(Tenant $tenant, ContractStatusEnum $status): Contract
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        return Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
            'status' => $status,
        ]);
    }
}
