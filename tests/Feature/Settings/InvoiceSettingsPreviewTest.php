<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class InvoiceSettingsPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_kegos_layout_preview_returns_html(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('settings.invoicing.preview'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_preview_loads_geist_through_font_face_while_pdf_view_does_not(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.preview'))->assertOk()->assertSee('@font-face', false)->assertSee('Geist', false);

        $pdfHtml = view('pdf.invoices.kegos', ['invoice' => \App\Services\Pdf\InvoicePreviewData::make(), 'qrDataUri' => null])->render();
        $this->assertStringNotContainsString('@font-face', $pdfHtml);
    }

    public function test_kegos_layout_preview_includes_pinned_footer_markup(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $response = $this->get(route('settings.invoicing.preview'));

        $response->assertOk();
        // Chrome's `pageNumber`/`totalPages` spans only resolve inside the print pipeline — the
        // HTML preview substitutes a literal "1/1" instead (see kegos-footer.blade.php).
        $response->assertSee('1/1', false);
    }

    public function test_kegos_layout_preview_shows_signature_image_when_tenant_has_one(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $media = $tenant->addMedia(UploadedFile::fake()->image('signature.png', 80, 40))->toMediaCollection('signature');
        $tenant->update(['signature_media_id' => $media->id]);

        $response = $this->get(route('settings.invoicing.preview'));

        $response->assertOk();
        $response->assertSee(__('app.invoice_pdf_signature'), false);
        $response->assertSee('data:image/png;base64,', false);
    }

    public function test_legacy_template_path_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get('/settings/invoicing/preview/bogus')->assertNotFound();
    }

    public function test_preview_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('settings.invoicing.preview'))->assertRedirect(route('login'));
    }

    public function test_preview_forbidden_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('settings.invoicing.preview'))->assertForbidden();
    }

    /**
     * Roles are tenant-editable — a custom read-only-accountant role could combine ViewInvoices
     * without ManageBillingSettings. `preview()` must gate on ManageBillingSettings alone,
     * matching the dedicated `signature()` stream route, not on Invoice visibility.
     */
    public function test_preview_forbidden_for_view_invoices_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $actor->givePermissionTo(PermissionEnum::ViewInvoices->value);

        $this->get(route('settings.invoicing.preview'))->assertForbidden();
    }
}
