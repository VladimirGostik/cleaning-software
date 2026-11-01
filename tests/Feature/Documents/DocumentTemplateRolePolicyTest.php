<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Policies\DocumentTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentTemplateRolePolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentTemplatePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentTemplatePolicy;
    }

    public function test_admin_can_do_everything(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $template));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $template));
        $this->assertTrue($this->policy->delete($user, $template));
    }

    public function test_secretary_can_manage_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Sekretárka', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $template));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $template));
        $this->assertTrue($this->policy->delete($user, $template));
    }

    public function test_accountant_is_view_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Účtovníčka', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $template));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $template));
        $this->assertFalse($this->policy->delete($user, $template));
    }

    public function test_vedouca_has_no_template_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Vedúca', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, $template));
        $this->assertFalse($this->policy->create($user));
    }

    public function test_cleaner_has_no_template_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $template = DocumentTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $template));
        $this->assertFalse($this->policy->delete($user, $template));
    }
}
