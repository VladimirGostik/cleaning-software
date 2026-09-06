<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Contracts\RendersContractPdf;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery\Expectation;
use Tests\TestCase;

final class ContractControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $contractableId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Zmluva o upratovaní',
            'number' => null,
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $contractableId,
            'contract_template_id' => null,
            'body' => 'Zmluvné podmienky.',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'notes' => null,
            'employment' => null,
        ], $overrides);
    }

    private function mockPdfRenderer(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersContractPdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_lists_tenant_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        Contract::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contracts.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Contracts/Index')->has('contracts.data', 2));
    }

    public function test_index_excludes_other_tenant_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->bindTenant($other);
        Contract::factory()->count(2)->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('contracts.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Contracts/Index')->has('contracts.data', 0));
    }

    public function test_index_forbidden_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('contracts.index'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // create / store
    // -------------------------------------------------------------------------

    public function test_create_exposes_form_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id]);
        TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);
        ContractTemplate::factory()->inactive()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contracts.create'));

        $response->assertInertia(
            fn (AssertableInertia $page) => $page->component('Contracts/Create')
                ->has('context.objects', 1)
                ->has('context.memberships', 2)
                ->has('context.templates', 1)
                ->has('context.tokens'),
        );
    }

    public function test_store_creates_contract_and_redirects_to_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('contracts.store'), $this->payload($object->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('contracts', ['tenant_id' => $tenant->id, 'title' => 'Zmluva o upratovaní']);
    }

    public function test_store_fails_when_fixed_term_missing_end_date(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('contracts.store'), $this->payload($object->id, ['end_date' => null]));

        $response->assertSessionHasErrors('end_date');
    }

    public function test_store_fails_when_employment_category_missing_employment_data(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('contracts.store'), $this->payload($membership->id, [
            'category' => ContractCategoryEnum::Employment->value,
            'contractable_type' => ContractableTypeEnum::TenantMembership->value,
            'employment' => null,
        ]));

        $response->assertSessionHasErrors('employment');
    }

    public function test_store_fails_for_cross_tenant_contractable_id(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $foreignObject = CleaningObject::factory()->create(['tenant_id' => $other->id]);

        $response = $this->post(route('contracts.store'), $this->payload($foreignObject->id));

        $response->assertSessionHasErrors('contractable_id');
    }

    public function test_store_fails_for_template_from_other_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $foreignTemplate = ContractTemplate::factory()->create(['tenant_id' => $other->id]);

        $response = $this->post(route('contracts.store'), $this->payload($object->id, ['contract_template_id' => $foreignTemplate->id]));

        $response->assertSessionHasErrors('contract_template_id');
    }

    // -------------------------------------------------------------------------
    // show / edit / update / destroy
    // -------------------------------------------------------------------------

    public function test_show_displays_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contracts.show', $contract));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Contracts/Show')->where('contract.id', $contract->id));
    }

    public function test_edit_keeps_inactive_current_object_in_options(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contracts.edit', $contract));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Contracts/Edit')->has('context.objects', 1));
    }

    public function test_update_persists_changes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forObject($object)->create(['tenant_id' => $tenant->id]);

        $this->put(route('contracts.update', $contract), $this->payload($object->id, ['title' => 'Nový titul']))
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame('Nový titul', $contract->title);
    }

    public function test_destroy_soft_deletes_draft_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('contracts.destroy', $contract))->assertRedirect();

        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    // -------------------------------------------------------------------------
    // sign / terminate
    // -------------------------------------------------------------------------

    public function test_sign_redirects_and_activates_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertRedirect(route('contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('active', $contract->status->value);
    }

    public function test_sign_active_contract_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertForbidden();
    }

    public function test_terminate_active_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.terminate', $contract), [
            'terminated_at' => now()->toDateString(),
            'termination_reason' => 'Dohoda',
        ])->assertRedirect(route('contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('terminated', $contract->status->value);
    }

    // -------------------------------------------------------------------------
    // pdf
    // -------------------------------------------------------------------------

    public function test_download_pdf_returns_attachment(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->create(['tenant_id' => $tenant->id, 'number' => 'ZML-2026-01']);
        $this->mockPdfRenderer();

        $response = $this->get(route('contracts.pdf', $contract));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=ZML-2026-01.pdf');
    }

    public function test_download_pdf_draft_without_number_uses_draft_filename(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $contract = Contract::factory()->create(['tenant_id' => $tenant->id, 'number' => null]);
        $this->mockPdfRenderer();

        $response = $this->get(route('contracts.pdf', $contract));

        $response->assertHeader('Content-Disposition', 'attachment; filename=draft.pdf');
    }

    public function test_download_pdf_without_permission_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $contract = Contract::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('contracts.pdf', $contract))->assertForbidden();
    }

    public function test_cross_tenant_show_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $contractB = Contract::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('contracts.show', $contractB->id))->assertNotFound();
    }
}
