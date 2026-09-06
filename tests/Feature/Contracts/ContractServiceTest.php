<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Events\ContractSigned;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\ContractService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ContractServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $overrides */
    private function upsertData(array $overrides = []): ContractUpsertData
    {
        return ContractUpsertData::from(array_merge([
            'title' => 'Zmluva o upratovaní',
            'number' => null,
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => null,
            'contract_template_id' => null,
            'body' => 'Zmluva medzi {{tenant.name}} a {{client.name}} pre {{object.name}}, {{object.address}}. Platná od {{contract.valid_from}}.',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'notes' => null,
            'employment' => null,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function test_create_resolves_object_and_client_tokens(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'CleanCo s.r.o.']);
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme s.r.o.']);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Kancelária', 'street' => 'Hlavná 1', 'city' => 'Bratislava', 'postal_code' => '811 01']);

        $contract = app(ContractService::class)->create($this->upsertData(['contractable_id' => $object->id]));

        $this->assertStringContainsString('CleanCo s.r.o.', $contract->body);
        $this->assertStringContainsString('Acme s.r.o.', $contract->body);
        $this->assertStringContainsString('Hlavná 1, 811 01 Bratislava', $contract->body);
        $this->assertStringContainsString(now()->format('d.m.Y'), $contract->body);
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
    }

    public function test_create_employment_persists_child_with_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $contract = app(ContractService::class)->create($this->upsertData([
            'category' => ContractCategoryEnum::Employment->value,
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'contractable_id' => $membership->id,
            'employment' => ['employment_type' => EmploymentContractTypeEnum::Dpp->value, 'weekly_hours' => 20],
        ]));

        $this->assertDatabaseHas('employment_contracts', [
            'contract_id' => $contract->id,
            'tenant_id' => $tenant->id,
            'employment_type' => 'dpp',
        ]);
    }

    public function test_create_fails_when_employment_category_targets_object(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->create($this->upsertData([
            'category' => ContractCategoryEnum::Employment->value,
            'contractable_id' => $object->id,
        ]));
    }

    public function test_create_fails_when_service_agreement_targets_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->create($this->upsertData([
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'contractable_id' => $membership->id,
        ]));
    }

    public function test_create_fails_for_cross_tenant_membership(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $foreignMembership = TenantMembership::factory()->create(['tenant_id' => $other->id]);

        $this->expectException(ModelNotFoundException::class);

        app(ContractService::class)->create($this->upsertData([
            'category' => ContractCategoryEnum::Employment->value,
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'contractable_id' => $foreignMembership->id,
        ]));
    }

    public function test_create_keeps_soft_deleted_template_name_resolvable(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Šablóna A']);

        $contract = app(ContractService::class)->create($this->upsertData([
            'contractable_id' => $object->id,
            'contract_template_id' => $template->id,
        ]));

        $template->delete();
        $contract->refresh();

        $this->assertSame('Šablóna A', $contract->contractTemplate?->name);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_re_resolves_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $contract = app(ContractService::class)->create($this->upsertData(['contractable_id' => $object->id, 'title' => 'Pôvodný názov']));

        $updated = app(ContractService::class)->update($contract, $this->upsertData([
            'contractable_id' => $object->id,
            'title' => 'Nový názov',
            'body' => '{{contract.title}}',
        ]));

        $this->assertSame('Nový názov', $updated->body);
    }

    public function test_update_fails_when_contract_not_editable(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->update($contract, $this->upsertData(['contractable_id' => $contract->contractable_id]));
    }

    public function test_update_removes_employment_child_when_category_changes_away(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $contract = app(ContractService::class)->create($this->upsertData([
            'category' => ContractCategoryEnum::Employment->value,
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'contractable_id' => $membership->id,
            'employment' => ['employment_type' => EmploymentContractTypeEnum::Dpp->value],
        ]));
        $this->assertDatabaseHas('employment_contracts', ['contract_id' => $contract->id]);

        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        app(ContractService::class)->update($contract, $this->upsertData([
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $object->id,
            'employment' => null,
        ]));

        $this->assertDatabaseMissing('employment_contracts', ['contract_id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // sign / terminate / delete
    // -------------------------------------------------------------------------

    public function test_sign_moves_draft_to_active_and_dispatches_event(): void
    {
        Event::fake([ContractSigned::class]);
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        app(ContractService::class)->sign($contract);

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
        $this->assertNotNull($contract->signed_at);
        Event::assertDispatched(ContractSigned::class, fn (ContractSigned $e) => $e->contractId === $contract->id);
    }

    public function test_signing_twice_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->sign($contract);
    }

    public function test_terminate_active_contract_with_reason(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        app(ContractService::class)->terminate($contract, new ContractTerminateData(
            terminated_at: now()->toDateString(),
            termination_reason: 'Dohoda strán',
        ));

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Terminated, $contract->status);
        $this->assertSame('Dohoda strán', $contract->termination_reason);
    }

    public function test_terminate_draft_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->terminate($contract, new ContractTerminateData(terminated_at: now()->toDateString()));
    }

    public function test_delete_draft_contract_soft_deletes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        app(ContractService::class)->delete($contract);

        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    public function test_delete_active_contract_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(ContractService::class)->delete($contract);
    }

    // -------------------------------------------------------------------------
    // paginate
    // -------------------------------------------------------------------------

    public function test_paginate_filters_by_status_category_and_contractable_type(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        Contract::factory()->draft()->create(['tenant_id' => $tenant->id, 'title' => 'Draft one']);
        Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'title' => 'Active one']);

        $request = Request::create('/', 'GET', ['filter' => ['status' => 'active']]);
        app()->instance('request', $request);
        $result = app(ContractService::class)->paginate($request);
        $this->assertSame(1, $result->total());

        $request = Request::create('/', 'GET', ['filter' => ['category' => 'service_agreement']]);
        app()->instance('request', $request);
        $result = app(ContractService::class)->paginate($request);
        $this->assertSame(2, $result->total());

        $request = Request::create('/', 'GET', ['filter' => ['contractable_type' => 'cleaning_object']]);
        app()->instance('request', $request);
        $result = app(ContractService::class)->paginate($request);
        $this->assertSame(2, $result->total());
    }

    public function test_paginate_search_matches_title_and_number(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        Contract::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Zmluva Alfa', 'number' => 'Z-1']);
        Contract::factory()->create(['tenant_id' => $tenant->id, 'title' => 'Iná zmluva', 'number' => 'Z-2']);

        $request = Request::create('/', 'GET', ['filter' => ['search' => 'Alfa']]);
        app()->instance('request', $request);
        $result = app(ContractService::class)->paginate($request);
        $this->assertSame(1, $result->total());

        $request = Request::create('/', 'GET', ['filter' => ['search' => 'Z-2']]);
        app()->instance('request', $request);
        $result = app(ContractService::class)->paginate($request);
        $this->assertSame(1, $result->total());
    }
}
