<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class DocumentTemplateControllerTest extends TestCase
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
            'type' => 'service_agreement',
            'body' => '<p>Text zmluvy {{client.name}}</p>',
            'show_header' => true,
            'show_footer' => true,
            'is_active' => true,
        ], $overrides);
    }

    public function test_index_lists_own_and_global_templates_but_not_foreign_ones(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        DocumentTemplate::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        DocumentTemplate::factory()->global()->create();
        DocumentTemplate::factory()->count(2)->create();

        $this->get(route('document-templates.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DocumentTemplates/Index', false)
                ->has('templates.data', 3)
                ->where('canCreateGlobal', false));
    }

    public function test_index_filters_by_scope_and_type(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        DocumentTemplate::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Firemná']);
        DocumentTemplate::factory()->global()->create(['name' => 'Globálna']);
        DocumentTemplate::factory()->global()->employment()->create(['name' => 'Pracovná']);

        $this->get(route('document-templates.index', ['filter' => ['scope' => 'global', 'type' => 'employment']]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('templates.data', 1)->where('templates.data.0.name', 'Pracovná'));

        $this->get(route('document-templates.index', ['filter' => ['search' => 'Firem']]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('templates.data', 1)->where('templates.data.0.scope', 'firm'));
    }

    public function test_index_forbidden_without_permission(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('document-templates.index'))->assertForbidden();
    }

    public function test_create_exposes_tokens_and_reads_query_defaults(): void
    {
        $this->actingAsTenantUser();

        $this->get(route('document-templates.create', ['type' => 'employment', 'employment_type' => 'tpp', 'global' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DocumentTemplates/Create', false)
                ->where('type', 'employment')
                ->where('employment_type', 'tpp')
                ->where('isGlobal', false)
                ->has('tokens.employment')
                ->has('letterhead')
                ->has('footer'));
    }

    public function test_store_creates_firm_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->post(route('document-templates.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('document_templates', ['tenant_id' => $tenant->id, 'name' => 'Zmluva o upratovaní']);
    }

    public function test_store_fails_without_name_and_with_unknown_tokens(): void
    {
        $this->actingAsTenantUser();

        $this->post(route('document-templates.store'), $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post(route('document-templates.store'), $this->payload(['body' => '<p>{{employee.name}}</p>']))->assertSessionHasErrors('body');
    }

    public function test_edit_exposes_template_state_tokens_and_abilities(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('document-templates.edit', $template))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DocumentTemplates/Edit', false)
                ->where('template.id', $template->id)
                ->where('template.scope', 'firm')
                ->where('template.can.update', true)
                ->has('template.tokens'));
    }

    public function test_global_template_is_read_only_for_a_firm_admin_who_can_customize_it(): void
    {
        $this->actingAsTenantUser();
        $global = DocumentTemplate::factory()->global()->create();

        $this->get(route('document-templates.edit', $global))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('template.can.update', false)
                ->where('template.can.customize', true));
    }

    public function test_customize_redirects_to_the_override_and_reset_returns_to_index(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $global = DocumentTemplate::factory()->global()->create();

        $this->post(route('document-templates.customize', $global))->assertRedirect();
        $override = DocumentTemplate::query()->whereNotNull('parent_id')->firstOrFail();

        $this->post(route('document-templates.customize', $global))->assertForbidden();
        $this->post(route('document-templates.reset', $override))->assertRedirect(route('document-templates.index'));
        $this->assertSoftDeleted('document_templates', ['id' => $override->id]);
    }

    public function test_update_persists_changes(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->put(route('document-templates.update', $template), $this->payload(['name' => 'Aktualizovaný názov']))->assertRedirect();

        $this->assertSame('Aktualizovaný názov', $template->fresh()->name);
    }

    public function test_destroy_soft_deletes_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->delete(route('document-templates.destroy', $template))->assertRedirect();

        $this->assertSoftDeleted('document_templates', ['id' => $template->id]);
    }

    public function test_cross_tenant_edit_returns_404(): void
    {
        $this->actingAsTenantUser();
        $foreign = DocumentTemplate::factory()->create();

        $this->get(route('document-templates.edit', $foreign))->assertNotFound();
    }

    public function test_preview_pdf_returns_a_pdf_for_unsaved_text(): void
    {
        $this->actingAsTenantUser();

        $this->post(route('document-templates.preview-pdf'), [
            'body' => '<p>{{tenant.name}}</p>', 'type' => 'service_agreement', 'show_header' => true, 'show_footer' => true,
        ])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
