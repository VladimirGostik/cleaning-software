<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // create — happy (client contract with placeholder)
    // -------------------------------------------------------------------------

    public function test_create_client_contract_replaces_token_in_body(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ACME s.r.o.']);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $data = ContractUpsertData::from([
            'title' => 'Service agreement',
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'contract_template_id' => $template->id,
            'body' => 'Contract for client {{client.name}}.',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $contract = app(ContractService::class)->create($data);

        $this->assertStringContainsString('ACME s.r.o.', $contract->body);
        $this->assertStringNotContainsString('{{client.name}}', $contract->body);
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
        $this->assertNull($contract->employmentContract);
    }

    // -------------------------------------------------------------------------
    // create — happy (employment contract)
    // -------------------------------------------------------------------------

    public function test_create_employment_contract_persists_employment_child(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $data = ContractUpsertData::from([
            'title' => 'Employment contract',
            'category' => ContractCategoryEnum::Employment->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
            'body' => 'Employment contract for {{employee.name}}.',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'employment' => [
                'employment_type' => EmploymentContractTypeEnum::Dpp->value,
                'position' => 'Cleaner',
                'hourly_rate' => '7.50',
                'monthly_salary' => null,
                'weekly_hours' => '20.00',
                'probation_end_date' => null,
            ],
        ]);

        $contract = app(ContractService::class)->create($data);

        $this->assertNotNull($contract->employmentContract);
        $this->assertSame(EmploymentContractTypeEnum::Dpp, $contract->employmentContract->employment_type);
        $this->assertDatabaseHas('employment_contracts', [
            'contract_id' => $contract->id,
            'position' => 'Cleaner',
        ]);
    }

    // -------------------------------------------------------------------------
    // create — failure: cross-tenant contractable_id
    // -------------------------------------------------------------------------

    public function test_create_fails_with_cross_tenant_contractable_id(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignObject = CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        $response = $this->post(route('contracts.store'), [
            'title' => 'Malicious contract',
            'category' => 'service_agreement',
            'term_type' => 'fixed',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $foreignObject->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertSessionHasErrors(['contractable_id']);
    }

    // -------------------------------------------------------------------------
    // create — failure: employment missing when category = employment
    // -------------------------------------------------------------------------

    public function test_create_fails_when_employment_null_for_employment_category(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();

        $response = $this->post(route('contracts.store'), [
            'title' => 'Employment contract',
            'category' => 'employment',
            'term_type' => 'fixed',
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            // 'employment' intentionally omitted
        ]);

        $response->assertSessionHasErrors(['employment']);
    }

    // -------------------------------------------------------------------------
    // create — failure: end_date missing for fixed term
    // -------------------------------------------------------------------------

    public function test_create_fails_when_end_date_missing_for_fixed_term(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('contracts.store'), [
            'title' => 'No end date',
            'category' => 'service_agreement',
            'term_type' => 'fixed',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            // 'end_date' omitted intentionally
        ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    // -------------------------------------------------------------------------
    // update — failure: end_date missing for fixed term
    // -------------------------------------------------------------------------

    public function test_update_fails_when_end_date_missing_for_fixed_term(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeDraftContract($tenant);

        $data = ContractUpsertData::from([
            'title' => 'Updated title',
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $contract->contractable_id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            // end_date intentionally omitted
        ]);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->update($contract, $data);
    }

    // -------------------------------------------------------------------------
    // update — failure: employment missing when category = employment
    // -------------------------------------------------------------------------

    public function test_update_fails_when_employment_null_for_employment_category(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();
        $contract = Contract::factory()->draft()->create([
            'tenant_id' => $tenant->id,
            'category' => ContractCategoryEnum::Employment,
            'contractable_id' => $membership->id,
            'contractable_type' => 'tenant_membership',
        ]);

        $data = ContractUpsertData::from([
            'title' => 'Updated',
            'category' => ContractCategoryEnum::Employment->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            // employment intentionally omitted
        ]);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->update($contract, $data);
    }

    // -------------------------------------------------------------------------
    // update — orphaned EmploymentContract deleted on category change
    // -------------------------------------------------------------------------

    public function test_update_deletes_employment_contract_when_category_changes(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $membership = TenantMembership::where('tenant_id', $tenant->id)->first();
        $contract = Contract::factory()->draft()->create([
            'tenant_id' => $tenant->id,
            'category' => ContractCategoryEnum::Employment,
            'contractable_id' => $membership->id,
            'contractable_type' => 'tenant_membership',
        ]);
        EmploymentContract::factory()->create(['contract_id' => $contract->id]);

        $this->assertDatabaseHas('employment_contracts', ['contract_id' => $contract->id]);

        // Switch to service_agreement without employment data
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $data = ContractUpsertData::from([
            'title' => 'Converted to service agreement',
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Indefinite->value,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            // employment is null
        ]);

        app(ContractService::class)->update($contract, $data);

        $this->assertDatabaseMissing('employment_contracts', ['contract_id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // sign — happy
    // -------------------------------------------------------------------------

    public function test_sign_transitions_draft_to_active(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeDraftContract($tenant);

        app(ContractService::class)->sign($contract);

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
        $this->assertNotNull($contract->signed_at);
    }

    // -------------------------------------------------------------------------
    // sign — failure: already active
    // -------------------------------------------------------------------------

    public function test_sign_throws_when_contract_already_active(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->sign($contract);
    }

    // -------------------------------------------------------------------------
    // sign — edge: sign twice
    // -------------------------------------------------------------------------

    public function test_sign_twice_fails_on_second_call(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeDraftContract($tenant);
        $service = app(ContractService::class);

        $service->sign($contract);
        $contract->refresh();

        $this->expectException(ValidationException::class);
        $service->sign($contract);
    }

    // -------------------------------------------------------------------------
    // terminate — happy
    // -------------------------------------------------------------------------

    public function test_terminate_sets_terminated_status_and_reason(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $data = ContractTerminateData::from([
            'terminated_at' => now()->toDateString(),
            'termination_reason' => 'Mutual agreement',
        ]);

        app(ContractService::class)->terminate($contract, $data);

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Terminated, $contract->status);
        $this->assertSame('Mutual agreement', $contract->termination_reason);
        $this->assertNotNull($contract->terminated_at);
    }

    // -------------------------------------------------------------------------
    // terminate — failure: Draft contract
    // -------------------------------------------------------------------------

    public function test_terminate_throws_when_contract_is_draft(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeDraftContract($tenant);

        $data = ContractTerminateData::from([
            'terminated_at' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->terminate($contract, $data);
    }

    // -------------------------------------------------------------------------
    // delete — happy: Draft soft-deleted
    // -------------------------------------------------------------------------

    public function test_delete_soft_deletes_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $contract = $this->makeDraftContract($tenant);

        app(ContractService::class)->delete($contract);

        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // delete — failure: Active contract
    // -------------------------------------------------------------------------

    public function test_delete_throws_when_contract_is_active(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $this->expectException(ValidationException::class);
        app(ContractService::class)->delete($contract);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeDraftContract(Tenant $tenant): Contract
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        return Contract::factory()->draft()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);
    }
}
