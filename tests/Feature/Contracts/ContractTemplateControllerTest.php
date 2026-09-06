<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ContractTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Zmluva o upratovaní',
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'body' => 'Text zmluvy {{client.name}}',
            'is_active' => true,
        ], $overrides);
    }

    public function test_index_lists_tenant_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        ContractTemplate::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contract-templates.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('ContractTemplates/Index')->has('templates.data', 2));
    }

    public function test_index_excludes_other_tenant_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        ContractTemplate::factory()->count(2)->create(['tenant_id' => $other->id]);
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('contract-templates.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('ContractTemplates/Index')->has('templates.data', 0));
    }

    public function test_index_forbidden_without_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('contract-templates.index'))->assertForbidden();
    }

    public function test_create_exposes_placeholder_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('contract-templates.create'));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('ContractTemplates/Create')->has('tokens'));
    }

    public function test_store_creates_template_and_redirects_to_show(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->post(route('contract-templates.store'), $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('contract_templates', ['tenant_id' => $tenant->id, 'name' => 'Zmluva o upratovaní']);
    }

    public function test_store_fails_without_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->post(route('contract-templates.store'), $this->payload(['name' => '']));

        $response->assertSessionHasErrors('name');
    }

    public function test_show_displays_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contract-templates.show', $template));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('ContractTemplates/Show')->where('template.id', $template->id));
    }

    public function test_edit_exposes_template_and_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('contract-templates.edit', $template));

        $response->assertInertia(fn (AssertableInertia $page) => $page->component('ContractTemplates/Edit')->has('tokens')->where('template.id', $template->id));
    }

    public function test_update_persists_changes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->put(route('contract-templates.update', $template), $this->payload(['name' => 'Aktualizovaný názov']))
            ->assertRedirect();

        $template->refresh();
        $this->assertSame('Aktualizovaný názov', $template->name);
    }

    public function test_destroy_soft_deletes_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = ContractTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('contract-templates.destroy', $template))->assertRedirect();

        $this->assertSoftDeleted('contract_templates', ['id' => $template->id]);
    }

    public function test_cross_tenant_show_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $templateB = ContractTemplate::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('contract-templates.show', $templateB->id))->assertNotFound();
    }
}
