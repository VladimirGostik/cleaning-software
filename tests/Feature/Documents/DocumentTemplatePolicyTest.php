<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function adminWithFirm(): User
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();

        return $this->actingAsTenantUser(tenant: $tenant, user: $user);
    }

    public function test_firm_admin_cannot_update_or_delete_a_global_template_even_as_owner(): void
    {
        $user = $this->adminWithFirm();
        $global = DocumentTemplate::factory()->global()->create();

        $this->assertFalse($user->can('update', $global));
        $this->assertFalse($user->can('delete', $global));
        $this->assertFalse($user->can('createGlobal', DocumentTemplate::class));

        $this->put(route('document-templates.update', $global), [
            'name' => 'x', 'type' => 'service_agreement', 'body' => '<p>x</p>', 'show_header' => true, 'show_footer' => true, 'is_active' => true,
        ])->assertForbidden();

        $this->post(route('document-templates.store-global'), [
            'name' => 'x', 'type' => 'service_agreement', 'body' => '<p>x</p>', 'show_header' => true, 'show_footer' => true, 'is_active' => true,
        ])->assertForbidden();
    }

    public function test_platform_admin_can_edit_and_create_global_templates(): void
    {
        $user = $this->adminWithFirm();
        $user->forceFill(['is_platform_admin' => true])->save();
        $global = DocumentTemplate::factory()->global()->create();

        $this->assertTrue($user->can('update', $global));
        $this->assertTrue($user->can('createGlobal', DocumentTemplate::class));

        $this->post(route('document-templates.store-global'), [
            'name' => 'Globálna', 'type' => 'nda', 'body' => '<p>{{tenant.name}}</p>', 'show_header' => true, 'show_footer' => true, 'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('document_templates', ['name' => 'Globálna', 'tenant_id' => null]);
    }

    public function test_firm_admin_manages_own_firm_templates(): void
    {
        $user = $this->adminWithFirm();
        $own = DocumentTemplate::factory()->create(['tenant_id' => current_tenant_id()]);

        $this->assertTrue($user->can('update', $own));
        $this->assertTrue($user->can('delete', $own));
    }

    public function test_foreign_firm_template_is_not_reachable(): void
    {
        $this->adminWithFirm();
        $foreign = DocumentTemplate::factory()->create();

        $this->get(route('document-templates.edit', $foreign))->assertNotFound();
    }

    public function test_cleaner_cannot_see_templates(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('document-templates.index'))->assertForbidden();
    }

    public function test_viewer_of_a_global_template_can_preview_without_edit_rights(): void
    {
        $this->actingAsTenantUser('Účtovníčka');

        $this->post(route('document-templates.preview'), [
            'body' => '<p>{{tenant.name}}</p>', 'type' => 'service_agreement', 'show_header' => true, 'show_footer' => true,
        ])->assertOk()->assertJsonPath('html', '<p>Ukážková s.r.o.</p>');
    }
}
