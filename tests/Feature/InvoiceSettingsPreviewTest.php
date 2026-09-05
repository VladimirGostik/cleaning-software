<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTemplateEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSettingsPreviewTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_owner_can_preview_classic_template(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('settings.invoicing.preview', ['template' => InvoiceTemplateEnum::Classic->value]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('FA-2024-0001');
        $response->assertSee('Demo Cleaning s.r.o.');
    }

    public function test_owner_can_preview_modern_template(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('settings.invoicing.preview', ['template' => InvoiceTemplateEnum::Modern->value]));

        $response->assertOk();
        $response->assertSee('FA-2024-0001');
    }

    public function test_owner_can_preview_minimal_template(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('settings.invoicing.preview', ['template' => InvoiceTemplateEnum::Minimal->value]));

        $response->assertOk();
        $response->assertSee('FA-2024-0001');
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_access_preview(): void
    {
        $response = $this->get(route('settings.invoicing.preview', ['template' => InvoiceTemplateEnum::Classic->value]));

        $response->assertRedirect(route('login'));
    }

    public function test_invalid_template_value_returns_404(): void
    {
        $this->actingAsTenantUser('Admin');

        $response = $this->get('/settings/invoicing/preview/invalid-template');

        $response->assertNotFound();
    }

    public function test_user_without_view_invoices_permission_cannot_access_preview(): void
    {
        // Interná upratovačka role has only ViewSchedule + ViewObjects — no invoice permissions.
        $user = $this->actingAsTenantUser('Interná upratovačka');

        $response = $this->get(route('settings.invoicing.preview', ['template' => InvoiceTemplateEnum::Classic->value]));

        $response->assertForbidden();
    }
}
