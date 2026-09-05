<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Contracts\RendersContractPdf;
use App\Enums\ContractStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ContractControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_contracts_for_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->makeContracts($tenant, 3);

        $response = $this->get(route('contracts.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Index')
            ->has('contracts.data', 3)
            ->has('statusOptions')
            ->has('categoryOptions')
            ->has('termTypeOptions'),
        );
    }

    public function test_index_excludes_other_tenant_contracts(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->makeContracts($tenant, 2);

        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherObject = CleaningObject::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);
        Contract::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'contractable_id' => $otherObject->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $response = $this->get(route('contracts.index'));

        $response->assertInertia(fn (Assert $page) => $page->has('contracts.data', 2));
    }

    public function test_index_403_for_user_without_view_contracts(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $response = $this->get(route('contracts.index'));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create page — membership picker only returns current-tenant memberships
    // -------------------------------------------------------------------------

    public function test_create_page_has_required_props(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('contracts.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Create')
            ->has('activeTemplates')
            ->has('objects')
            ->has('memberships')
            ->has('categoryOptions')
            ->has('termTypeOptions')
            ->has('employmentTypeOptions')
            ->has('clientContractTokens')
            ->has('employmentContractTokens'),
        );
    }

    public function test_create_page_memberships_scoped_to_current_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $currentTenantMemberCount = TenantMembership::where('tenant_id', $tenant->id)->where('is_active', true)->count();

        // Create a membership for a different tenant — must not appear in picker
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        $response = $this->get(route('contracts.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Create')
            ->has('memberships', $currentTenantMemberCount),
        );
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_contract_and_redirects_to_show(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('contracts.store'), [
            'title' => 'New service contract',
            'category' => 'service_agreement',
            'term_type' => 'fixed',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'body' => 'Contract body text.',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contracts', [
            'tenant_id' => $tenant->id,
            'title' => 'New service contract',
        ]);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('contracts.store'), []);

        $response->assertSessionHasErrors(['title', 'category', 'term_type', 'contractable_type', 'contractable_id', 'body', 'valid_from']);
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_renders_contract_detail(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1);

        $response = $this->get(route('contracts.show', $contract));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Show')
            ->has('contract'),
        );
    }

    // -------------------------------------------------------------------------
    // edit page — membership picker only returns current-tenant memberships
    // -------------------------------------------------------------------------

    public function test_edit_page_memberships_scoped_to_current_tenant(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $currentTenantMemberCount = TenantMembership::where('tenant_id', $tenant->id)->where('is_active', true)->count();

        // Create a membership for a different tenant — must not appear in picker
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);

        [$contract] = $this->makeContracts($tenant, 1);

        $response = $this->get(route('contracts.edit', $contract));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contracts/Edit')
            ->has('memberships', $currentTenantMemberCount),
        );
    }

    // -------------------------------------------------------------------------
    // sign (POST /contracts/{contract}/sign)
    // -------------------------------------------------------------------------

    public function test_sign_transitions_to_active_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.sign', $contract));

        $response->assertRedirect(route('contracts.show', $contract));
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Active->value,
        ]);
    }

    public function test_sign_fails_when_contract_already_active(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.sign', $contract));

        // Policy denies: canBeSigned() = false for Active
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // terminate (POST /contracts/{contract}/terminate)
    // -------------------------------------------------------------------------

    public function test_terminate_transitions_to_terminated_and_redirects(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1, ContractStatusEnum::Active);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
            'termination_reason' => 'Mutual agreement',
        ]);

        $response->assertRedirect(route('contracts.show', $contract));
        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'status' => ContractStatusEnum::Terminated->value,
        ]);
    }

    public function test_terminate_fails_when_contract_is_draft(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1, ContractStatusEnum::Draft);

        $response = $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
        ]);

        // Policy denies: canBeTerminated() = false for Draft
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_draft_contract(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1, ContractStatusEnum::Draft);

        $response = $this->delete(route('contracts.destroy', $contract));

        $response->assertRedirect(route('contracts.index'));
        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // pdf endpoint — mocks RendersContractPdf
    // -------------------------------------------------------------------------

    public function test_pdf_streams_download_response(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        [$contract] = $this->makeContracts($tenant, 1);

        $this->instance(RendersContractPdf::class, new class implements RendersContractPdf
        {
            public function render(Contract $contract): string
            {
                return '%PDF-1.4 fake pdf bytes';
            }
        });

        $response = $this->get(route('contracts.pdf', $contract));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_403_without_view_contracts_permission(): void
    {
        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_id' => $object->id,
            'contractable_type' => 'cleaning_object',
        ]);

        $response = $this->get(route('contracts.pdf', $contract));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<int, Contract>
     */
    private function makeContracts(Tenant $tenant, int $count, ContractStatusEnum $status = ContractStatusEnum::Draft): array
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $contracts = [];
        for ($i = 0; $i < $count; $i++) {
            $contracts[] = Contract::factory()->create([
                'tenant_id' => $tenant->id,
                'contractable_id' => $object->id,
                'contractable_type' => 'cleaning_object',
                'status' => $status,
            ]);
        }

        return $contracts;
    }
}
