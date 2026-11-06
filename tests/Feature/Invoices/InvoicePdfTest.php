<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Mockery\Expectation;
use Tests\TestCase;

final class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function mockPdfRenderer(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_download_pdf_returns_pdf_for_issued_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_download_pdf_draft_without_number_uses_draft_filename(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=draft.pdf');
    }

    public function test_download_pdf_missing_iban_still_renders(): void
    {
        $tenant = Tenant::factory()->create(['iban' => null]);
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'supplier_iban' => null]);
        $this->mockPdfRenderer();

        $this->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_download_pdf_with_unsafe_characters_in_number_produces_well_formed_header(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'number' => 'FA"2026/01']);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringStartsWith('attachment; filename=', $disposition);
        // The raw invoice number's `"` and `/` must never survive into the header value.
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertSame('attachment; filename=FA202601.pdf', $disposition);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_download_pdf_unauthenticated_redirects_to_login(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.pdf', $invoice))->assertRedirect(route('login'));
    }

    public function test_download_pdf_without_permission_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.pdf', $invoice))->assertForbidden();
    }

    public function test_download_pdf_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('invoices.pdf', $invoiceB->id))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // supplier snapshot -> template rendering
    // -------------------------------------------------------------------------

    private function invoiceWithFullSupplierSnapshot(): Invoice
    {
        $tenant = Tenant::factory()->create([
            'address_line' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'iban' => 'SK8975000000000123456789',
            'swift_bic' => 'TATRSKBX',
            'contact_email' => 'fakturacia@democleaning.sk',
            'contact_phone' => '+421900000000',
        ]);
        $this->bindTenant($tenant);

        return app(InvoiceService::class)->create(new InvoiceUpsertData(
            client_id: null,
            cleaning_object_id: null,
            type: InvoiceTypeEnum::OneOff,
            issue_date: now()->toDateString(),
            delivery_date: now()->toDateString(),
            due_date: now()->addDays(14)->toDateString(),
            period_from: null,
            period_to: null,
            customer_name: 'Acme s.r.o.',
            customer_representative: null,
            customer_ico: null,
            customer_dic: null,
            customer_vat_number: null,
            customer_street: null,
            customer_city: null,
            customer_postal_code: null,
            customer_country: null,
            customer_email: null,
            note: 'Ďakujeme za spoluprácu',
            items: [new InvoiceItemData(id: null, description: 'Upratovanie', quantity: 1, unit: null, unit_price: 100)],
            constant_symbol: null,
            specific_symbol: null,
            header_text: null,
            footer_text: null,
        ));
    }

    /** @param array<string, mixed> $overrides */
    private function boundInvoice(array $overrides = []): Invoice
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        return Invoice::factory()->create(array_merge(['tenant_id' => $tenant->id], $overrides));
    }

    public function test_kegos_layout_renders_header_text_directly_above_items_table(): void
    {
        $invoice = $this->boundInvoice(['header_text' => "Line A\nLine B"]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $headerPos = strpos($html, 'Line A');
        $this->assertNotFalse($headerPos);
        $this->assertGreaterThan(strpos($html, __('app.invoice_pdf_due_date').':'), $headerPos);
        $this->assertGreaterThan(strpos($html, e($invoice->customer_name)), $headerPos);
        $this->assertLessThan(strpos($html, 'class="kg-items'), $headerPos);
        $this->assertMatchesRegularExpression('/white-space:\s*pre-line;[^>]*>\s*Line A\nLine B/', $html);
    }

    public function test_preview_streams_inline_without_caching(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.preview', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_download_pdf_is_attachment_and_not_cached(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $this->mockPdfRenderer();

        $response = $this->get(route('invoices.pdf', $invoice));

        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_preview_unauthenticated_redirects_to_login(): void
    {
        $tenant = Tenant::factory()->create();
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.preview', $invoice))->assertRedirect(route('login'));
    }

    public function test_preview_without_permission_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->get(route('invoices.preview', $invoice))->assertForbidden();
    }

    public function test_preview_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantA);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id]);

        $this->get(route('invoices.preview', $invoiceB->id))->assertNotFound();
    }

    public function test_kegos_layout_prints_country_names_and_keeps_unknown_codes(): void
    {
        app()->setLocale('sk');
        $invoice = $this->boundInvoice(['supplier_country' => 'SK', 'customer_country' => 'XX']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString('Slovensko<br>', $html);
        $this->assertStringContainsString('XX<br>', $html);
    }

    public function test_kegos_layout_places_pay_block_above_header_text_and_items(): void
    {
        $invoice = $this->boundInvoice(['header_text' => 'Line A', 'supplier_iban' => 'SK8975000000000123456789']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $payPos = strpos($html, 'pay-cell"><div class="l">');
        $this->assertNotFalse($payPos);
        $this->assertGreaterThan(strrpos(substr($html, 0, (int) strpos($html, 'class="kg-items')), __('app.invoice_pdf_due_date').':'), $payPos);
        $this->assertLessThan(strpos($html, 'Line A'), $payPos);
        $this->assertLessThan(strpos($html, 'class="kg-items'), strpos($html, 'Line A'));
    }

    public function test_kegos_layout_always_shows_unit_column_and_prints_unit(): void
    {
        $invoice = $this->boundInvoice();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id, 'unit' => 'mes.']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice->load('items'), 'qrDataUri' => null])->render();

        $this->assertStringContainsString('<th>'.__('app.invoice_pdf_item_unit').'</th>', $html);
        $this->assertStringContainsString('<td>mes.</td>', $html);
    }

    public function test_kegos_layout_renders_full_supplier_snapshot(): void
    {
        $invoice = $this->invoiceWithFullSupplierSnapshot();

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString('viewBox="8 12 711.8 78"', $html);
        $this->assertStringContainsString('height: 5mm', $html);
        $this->assertStringContainsString('font-size: 14pt', $html);
        $this->assertStringContainsString('Hlavná 1', $html);
        $this->assertStringContainsString('811 01 Bratislava', $html);
        $this->assertStringContainsString('2012345678', $html);
        $this->assertStringContainsString('SK2012345678', $html);
        $this->assertStringContainsString('SK8975000000000123456789', $html);
        $this->assertStringContainsString('TATRSKBX', $html);
        $this->assertStringContainsString(__('app.invoice_pdf_ico'), $html);
        $this->assertStringContainsString(__('app.invoice_pdf_dic'), $html);
        $this->assertStringContainsString(__('app.invoice_pdf_vat_number'), $html);
    }

    public function test_kegos_layout_renders_note_when_present_and_omits_when_null(): void
    {
        $withNote = $this->invoiceWithFullSupplierSnapshot();
        $htmlWithNote = View::make('pdf.invoices.kegos', ['invoice' => $withNote, 'qrDataUri' => null])->render();
        $this->assertStringContainsString('Ďakujeme za spoluprácu', $htmlWithNote);

        $withoutNote = $this->boundInvoice(['note' => null]);
        $htmlWithoutNote = View::make('pdf.invoices.kegos', ['invoice' => $withoutNote, 'qrDataUri' => null])->render();
        $this->assertStringNotContainsString(__('app.invoice_pdf_note'), $htmlWithoutNote);
    }

    public function test_kegos_layout_renders_payment_strip_when_iban_present_and_omits_when_null(): void
    {
        $withIban = $this->boundInvoice(['supplier_iban' => 'SK8975000000000123456789']);
        $htmlWithIban = View::make('pdf.invoices.kegos', ['invoice' => $withIban, 'qrDataUri' => null])->render();
        $this->assertStringContainsString('SK8975000000000123456789', $htmlWithIban);
        $this->assertStringContainsString(__('app.invoice_pdf_amount_due'), $htmlWithIban);

        $withoutIban = $this->boundInvoice(['supplier_iban' => null]);
        $htmlWithoutIban = View::make('pdf.invoices.kegos', ['invoice' => $withoutIban, 'qrDataUri' => null])->render();
        $this->assertStringNotContainsString(__('app.invoice_pdf_amount_due'), $htmlWithoutIban);
    }

    public function test_kegos_layout_omits_qr_block_when_qr_data_uri_is_null(): void
    {
        $invoice = $this->boundInvoice();

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString('PAY by square', $html);
    }

    public function test_kegos_layout_renders_qr_block_when_present(): void
    {
        $invoice = $this->boundInvoice();

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => 'data:image/png;base64,Zm9v'])->render();

        $this->assertStringContainsString('PAY by square', $html);
        $this->assertStringContainsString('data:image/png;base64,Zm9v', $html);
    }

    public function test_signature_block_renders_when_data_uri_present(): void
    {
        $invoice = $this->boundInvoice();
        $dataUri = 'data:image/png;base64,Zm9v';

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null, 'signatureDataUri' => $dataUri])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_signature'), $html);
        $this->assertStringContainsString('<img src="'.$dataUri.'"', $html);
    }

    public function test_signature_block_absent_without_data_uri_variable(): void
    {
        $invoice = $this->boundInvoice();

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_signature'), $html);
    }

    // -------------------------------------------------------------------------
    // classic pinned footer (Chrome footerView template)
    // -------------------------------------------------------------------------

    public function test_kegos_footer_renders_supplier_contact_info(): void
    {
        $invoice = $this->boundInvoice([
            'supplier_contact_phone' => '+421900000000',
            'supplier_contact_email' => 'fakturacia@democleaning.sk',
            'issued_by_name' => null,
        ]);

        $html = View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice, 'preview' => false])->render();

        $this->assertStringContainsString('+421 900 000 000', $html);
        $this->assertStringContainsString('fakturacia@democleaning.sk', $html);
    }

    public function test_kegos_footer_renders_issued_by_when_present(): void
    {
        $invoice = $this->boundInvoice(['issued_by_name' => 'Jana Nováková']);

        $html = View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice, 'preview' => false])->render();

        // Label is now a bold `<strong>` tag around just the label, not the whole "label: value" run.
        $this->assertStringContainsString(__('app.invoice_pdf_issued_by').':</strong> Jana Nováková', $html);
        $this->assertStringContainsString('<strong>'.__('app.invoice_pdf_issued_by'), $html);
    }

    public function test_kegos_footer_omits_issued_by_label_when_null(): void
    {
        $invoice = $this->boundInvoice([
            'issued_by_name' => null,
            'supplier_contact_phone' => '+421900000000',
            'supplier_contact_email' => 'fakturacia@democleaning.sk',
        ]);

        $html = View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice, 'preview' => false])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_issued_by'), $html);
        $this->assertStringNotContainsString(__('app.invoice_pdf_issued_by').':', $html);
        $this->assertStringContainsString('+421 900 000 000', $html);
        $this->assertStringContainsString('fakturacia@democleaning.sk', $html);
    }

    // -------------------------------------------------------------------------
    // classic block 3 — constant/specific symbol (previously dropped)
    // -------------------------------------------------------------------------

    public function test_kegos_layout_renders_constant_and_specific_symbol_when_present(): void
    {
        $invoice = $this->boundInvoice(['constant_symbol' => '0308', 'specific_symbol' => '1234567890']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_constant_symbol').': 0308', $html);
        $this->assertStringContainsString(__('app.invoice_pdf_specific_symbol').': 1234567890', $html);
    }

    public function test_kegos_layout_omits_constant_and_specific_symbol_when_null(): void
    {
        $invoice = $this->boundInvoice(['constant_symbol' => null, 'specific_symbol' => null]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_constant_symbol'), $html);
        $this->assertStringNotContainsString(__('app.invoice_pdf_specific_symbol'), $html);
    }

    // -------------------------------------------------------------------------
    // classic status chrome — issued/paid stay chrome-free, everything else warns
    // -------------------------------------------------------------------------

    public function test_kegos_layout_omits_status_chrome_for_issued_and_paid(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        foreach (['issued', 'paid'] as $state) {
            /** @var Invoice $invoice */
            $invoice = Invoice::factory()->{$state}()->create(['tenant_id' => $tenant->id]);

            $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

            $this->assertStringNotContainsString(__('app.invoice_pdf_status').':', $html, $state);
        }
    }

    public function test_kegos_layout_renders_status_chrome_for_draft_overdue_and_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        foreach (['draft', 'cancelled'] as $state) {
            /** @var Invoice $invoice */
            $invoice = $state === 'draft'
                ? Invoice::factory()->create(['tenant_id' => $tenant->id])
                : Invoice::factory()->{$state}()->create(['tenant_id' => $tenant->id]);

            $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

            $this->assertStringContainsString(__('app.invoice_pdf_status').': '.$invoice->status->label(), $html, $state);
        }

        // Overdue is payment-timing state, not print-time state (owner decision) — no banner.
        /** @var Invoice $overdueInvoice */
        $overdueInvoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        $overdueHtml = View::make('pdf.invoices.kegos', ['invoice' => $overdueInvoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_status').':', $overdueHtml);
    }

    // -------------------------------------------------------------------------
    // classic block 4 — customer DIC, billing period, payment type (previously dropped)
    // -------------------------------------------------------------------------

    public function test_kegos_layout_renders_customer_dic_when_present(): void
    {
        $invoice = $this->boundInvoice(['customer_dic' => '2098765432']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_dic').': 2098765432', $html);
    }

    public function test_kegos_layout_omits_customer_dic_when_null(): void
    {
        $invoice = $this->boundInvoice(['customer_dic' => null, 'supplier_dic' => null]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_dic'), $html);
    }

    public function test_kegos_layout_renders_billing_period_when_present(): void
    {
        $invoice = $this->boundInvoice(['period_from' => '2026-08-01', 'period_to' => '2026-08-31']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_period'), $html);
        $this->assertStringContainsString('1. 8. 2026', $html);
        $this->assertStringContainsString('31. 8. 2026', $html);
    }

    public function test_kegos_layout_omits_billing_period_when_null(): void
    {
        $invoice = $this->boundInvoice(['period_from' => null, 'period_to' => null]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_period'), $html);
    }

    // payment_type has a non-nullable DB default (`transfer`) — no absence case exists to test.
    public function test_kegos_layout_renders_payment_type_label(): void
    {
        $invoice = $this->boundInvoice(['payment_type' => PaymentTypeEnum::Cash]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_payment_type').':', $html);
        $this->assertStringContainsString($invoice->payment_type->label(), $html);
    }

    // -------------------------------------------------------------------------
    // classic block 6 — VAT recapitulation total column (previously dropped)
    // -------------------------------------------------------------------------

    public function test_kegos_layout_renders_vat_recap_total_column_when_vat_payer(): void
    {
        $invoice = $this->boundInvoice([
            'is_vat_payer' => true,
            'vat_rate' => '23.00',
            'vat_amount' => '23.00',
            'total' => '123.00',
            'vat_breakdown' => [
                ['rate' => 23.0, 'base' => 100.0, 'vat' => 23.0, 'total' => 123.0],
            ],
        ]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_vat_recap_total'), $html);
    }

    public function test_kegos_layout_omits_vat_recap_total_column_for_non_vat_payer(): void
    {
        $invoice = $this->boundInvoice(['is_vat_payer' => false, 'vat_breakdown' => null]);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        // "Total" alone collides with invoice_pdf_item_total / invoice_pdf_total labels
        // elsewhere on the page — assert on the recap section's unique title instead, which
        // proves the whole recap block (incl. the total column) is absent.
        $this->assertStringNotContainsString(__('app.invoice_vat_recap_title'), $html);
    }

    // -------------------------------------------------------------------------
    // classic fidelity fix — currency symbol formatting, non-payer totals shape,
    // QR/strip overlap geometry, signature label styling
    // -------------------------------------------------------------------------

    public function test_kegos_layout_renders_currency_symbol_not_iso_code(): void
    {
        $invoice = $this->invoiceWithFullSupplierSnapshot();

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString("100,00\u{00A0}€", $html);
        $this->assertStringNotContainsString(' EUR', $html);
        // Payer path intact: subtotal row still present alongside the single-total change.
        $this->assertStringContainsString(__('app.invoice_pdf_subtotal'), $html);
    }

    public function test_kegos_layout_drops_subtotal_row_for_non_vat_payer(): void
    {
        $invoice = $this->boundInvoice(['is_vat_payer' => false, 'vat_breakdown' => null, 'rounding_amount' => '0.00']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringNotContainsString(__('app.invoice_pdf_subtotal'), $html);
        $this->assertStringContainsString(__('app.invoice_pdf_total'), $html);
    }

    public function test_kegos_layout_non_vat_payer_deposit_path_not_regressed(): void
    {
        $invoice = $this->boundInvoice(['is_vat_payer' => false, 'deposit' => '10.00']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

        $this->assertStringContainsString(__('app.invoice_pdf_deposit'), $html);
        $this->assertStringContainsString(__('app.invoice_pdf_balance_due'), $html);
    }

    public function test_kegos_layout_payment_panel_places_qr_beside_payment_details_only_when_present(): void
    {
        $withQr = $this->boundInvoice(['supplier_iban' => 'SK8975000000000123456789']);
        $htmlWithQr = View::make('pdf.invoices.kegos', [
            'invoice' => $withQr,
            'qrDataUri' => 'data:image/png;base64,Zm9v',
        ])->render();
        $this->assertStringContainsString('class="block pay panel"', $htmlWithQr);
        $this->assertStringContainsString('alt="PAY by square QR"', $htmlWithQr);

        $withoutQr = $this->boundInvoice(['supplier_iban' => 'SK8975000000000123456789']);
        $htmlWithoutQr = View::make('pdf.invoices.kegos', ['invoice' => $withoutQr, 'qrDataUri' => null])->render();
        $this->assertStringContainsString('class="block pay panel"', $htmlWithoutQr);
        $this->assertStringNotContainsString('alt="PAY by square QR"', $htmlWithoutQr);
    }

    public function test_kegos_layout_renders_sentence_case_signature_label(): void
    {
        $invoice = $this->boundInvoice();

        $html = View::make('pdf.invoices.kegos', [
            'invoice' => $invoice,
            'qrDataUri' => null,
            'signatureDataUri' => 'data:image/png;base64,Zm9v',
        ])->render();

        $this->assertStringContainsString('kg-label">'.__('app.invoice_pdf_signature').':', $html);
    }

    public function test_kegos_layout_renders_logo_orange_rule_and_geist_without_legacy_colours(): void
    {
        $html = View::make('pdf.invoices.kegos', ['invoice' => $this->boundInvoice(), 'qrDataUri' => null])->render();

        $this->assertStringContainsString('aria-label="Kegos Clean"', $html);
        $this->assertStringContainsString('class="kg-rule"', $html);
        $this->assertStringContainsString("'Geist'", $html);
        $this->assertStringNotContainsString('#1e40af', $html);
        $this->assertStringNotContainsString('#dbeaf5', $html);
    }

    public function test_kegos_footer_renders_supplier_identity_and_page_counter(): void
    {
        $invoice = $this->boundInvoice([
            'supplier_name' => 'Footer Supplier s.r.o.',
            'supplier_ico' => '12345678',
            'supplier_dic' => '2012345678',
            'supplier_registration_info' => 'Zapísaná v OR SR, vložka 1/B',
        ]);

        $html = View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice, 'preview' => false])->render();

        $this->assertStringContainsString('Footer Supplier s.r.o.', $html);
        $this->assertStringContainsString('12345678', $html);
        $this->assertStringContainsString('Zapísaná v OR SR, vložka 1/B', $html);
        $this->assertStringContainsString('class="pageNumber"', $html);
        $this->assertStringContainsString('class="totalPages"', $html);
    }

    public function test_kegos_footer_brand_line_follows_config(): void
    {
        $invoice = $this->boundInvoice();

        config(['documents.show_brand_line' => false]);
        $this->assertStringNotContainsString(__('app.pdf_under_brand'), View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice])->render());

        config(['documents.show_brand_line' => true]);
        $this->assertStringContainsString(__('app.pdf_under_brand'), View::make('pdf.invoices.kegos-footer', ['invoice' => $invoice])->render());
    }

    public function test_kegos_layout_titles_a_real_credit_note_and_keeps_item_amounts_negative(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $original = Invoice::factory()->issued()->vatPayer()->create(['tenant_id' => $tenant->id, 'supplier_name' => 'Original Snapshot s.r.o.']);
        InvoiceItem::factory()->withVat()->create(['tenant_id' => $tenant->id, 'invoice_id' => $original->id, 'description' => 'Upratovanie', 'quantity' => 2, 'unit_price' => 30, 'line_base' => 60]);

        app(InvoiceService::class)->cancel($original, null);
        $creditNote = Invoice::where('credited_invoice_id', $original->id)->firstOrFail()->load(['items', 'creditedInvoice']);

        $html = View::make('pdf.invoices.kegos', ['invoice' => $creditNote, 'qrDataUri' => null])->render();

        $this->assertStringContainsString('kg-doc-title">'.__('app.invoice_credit_note').' '.$creditNote->number, $html);
        $this->assertStringNotContainsString('kg-doc-title">'.__('app.invoice_pdf_title').' ', $html);
        $this->assertStringContainsString(__('app.invoice_credit_note_for').' '.$original->number, $html);
        $this->assertStringContainsString('Original Snapshot s.r.o.', $html);
        $this->assertCount(1, $creditNote->items);
        foreach ($creditNote->items as $item) {
            $this->assertLessThan(0, (float) $item->line_base);
            $this->assertStringContainsString($creditNote->currency->format((float) $item->line_base), $html);
        }
    }

    public function test_kegos_layout_titles_regular_invoices_as_invoice_for_every_type(): void
    {
        foreach (InvoiceTypeEnum::cases() as $type) {
            $invoice = $this->boundInvoice(['type' => $type]);

            $html = View::make('pdf.invoices.kegos', ['invoice' => $invoice, 'qrDataUri' => null])->render();

            $this->assertStringContainsString('kg-doc-title">'.__('app.invoice_pdf_title').' ', $html, $type->value);
        }
    }

    public function test_kegos_items_table_right_aligns_numeric_headers_and_zeroes_edge_padding(): void
    {
        $html = View::make('pdf.invoices.kegos', ['invoice' => $this->boundInvoice(), 'qrDataUri' => null])->render();

        $this->assertStringContainsString('<th class="num">'.__('app.invoice_pdf_item_total').'</th>', $html);
        $this->assertStringContainsString('.kg-items th.num, .kg-items td.num { text-align: right; }', $html);
        $this->assertStringContainsString('.kg-items th:first-child, .kg-items td:first-child, .kg-items th:last-child, .kg-items td:last-child { padding-inline: 8px; }', $html);
    }
}
