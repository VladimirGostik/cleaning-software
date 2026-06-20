<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\SubscriptionPlanEnum;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContractTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Vlastník — all template permissions
    // -------------------------------------------------------------------------

    public function test_vlastnik_can_list_contract_templates(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contract-templates.index'));

        $response->assertOk();
    }

    public function test_vlastnik_can_view_single_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contract-templates.show', $template));

        $response->assertOk();
    }

    public function test_vlastnik_can_access_create_page(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contract-templates.create'));

        $response->assertOk();
    }

    public function test_vlastnik_can_store_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->post(route('contract-templates.store'), [
            'name' => 'Test template',
            'category' => 'service_agreement',
            'body' => 'Template body with {{tenant.name}}',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contract_templates', ['name' => 'Test template']);
    }

    public function test_vlastnik_can_delete_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('contract-templates.destroy', $template));

        $response->assertRedirect(route('contract-templates.index'));
        $this->assertSoftDeleted('contract_templates', ['id' => $template->id]);
    }

    // -------------------------------------------------------------------------
    // Sekretárka — full template access (view/create/edit/delete templates)
    // -------------------------------------------------------------------------

    public function test_sekretarka_can_list_contract_templates(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contract-templates.index'));

        $response->assertOk();
    }

    public function test_sekretarka_can_create_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->post(route('contract-templates.store'), [
            'name' => 'Secretary template',
            'category' => 'nda',
            'body' => 'NDA body',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contract_templates', ['name' => 'Secretary template']);
    }

    public function test_sekretarka_can_update_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Sekretárka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->put(route('contract-templates.update', $template), [
            'name' => 'Updated name',
            'category' => 'gdpr',
            'body' => 'GDPR body',
            'is_active' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contract_templates', ['id' => $template->id, 'name' => 'Updated name']);
    }

    // -------------------------------------------------------------------------
    // Účtovníčka — view only
    // -------------------------------------------------------------------------

    public function test_uctovnicka_can_list_contract_templates(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contract-templates.index'));

        $response->assertOk();
    }

    public function test_uctovnicka_cannot_access_create_template_page(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('contract-templates.create'));

        $response->assertForbidden();
    }

    public function test_uctovnicka_cannot_store_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->post(route('contract-templates.store'), [
            'name' => 'Accountant template',
            'category' => 'service_agreement',
            'body' => 'body',
            'is_active' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_uctovnicka_cannot_delete_contract_template(): void
    {
        $user = $this->actingAsTenantUser('Účtovníčka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->delete(route('contract-templates.destroy', $template));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Unauthenticated
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_redirected_from_templates_index(): void
    {
        $response = $this->get(route('contract-templates.index'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Cross-tenant isolation — template from other tenant returns 404
    // -------------------------------------------------------------------------

    public function test_contract_template_from_other_tenant_returns_404(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $foreignTemplate = ContractTemplate::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->get(route('contract-templates.show', $foreignTemplate));

        $response->assertNotFound();
    }
}
