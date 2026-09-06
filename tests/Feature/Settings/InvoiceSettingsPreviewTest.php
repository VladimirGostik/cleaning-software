<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSettingsPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_classic_template_preview_returns_html(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('settings.invoicing.preview', ['template' => 'classic']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_modern_template_preview_returns_html(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.preview', ['template' => 'modern']))->assertOk();
    }

    public function test_minimal_template_preview_returns_html(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.preview', ['template' => 'minimal']))->assertOk();
    }

    public function test_invalid_template_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get('/settings/invoicing/preview/bogus')->assertNotFound();
    }

    public function test_preview_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('settings.invoicing.preview', ['template' => 'classic']))->assertRedirect(route('login'));
    }

    public function test_preview_forbidden_without_view_invoices_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('settings.invoicing.preview', ['template' => 'classic']))->assertForbidden();
    }
}
