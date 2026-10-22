<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LibXMLError;
use Tests\TestCase;

final class PohodaExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['ico' => '12345678']);
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(string $state, string $issueDate, array $attributes = [], ?Tenant $tenant = null): Invoice
    {
        $tenant ??= $this->tenant;
        $factory = match ($state) {
            'paid' => Invoice::factory()->paid(),
            'overdue' => Invoice::factory()->overdue(),
            'cancelled' => Invoice::factory()->cancelled(),
            default => Invoice::factory()->issued(),
        };
        $invoice = $factory->create($attributes + ['tenant_id' => $tenant->id, 'issue_date' => $issueDate]);
        InvoiceItem::factory()->create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'position' => 0]);

        return $invoice;
    }

    /** @return list<string> */
    private function exportedNumbers(string $xml): array
    {
        $doc = new DOMDocument;
        $this->assertTrue($doc->loadXML($xml));

        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $valid = $doc->schemaValidate(base_path('tests/Fixtures/pohoda/xsd/data.xsd'));
        $messages = array_map(fn (LibXMLError $e): string => 'line '.$e->line.': '.trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($valid, "Pohoda XSD validation failed:\n".implode("\n", $messages));

        $ids = [];
        foreach ($doc->getElementsByTagName('dataPackItem') as $node) {
            $ids[] = $node->getAttribute('id');
        }

        return $ids;
    }

    private function url(string $from = '2026-09-01', string $to = '2026-09-30', string $route = 'invoices.export.pohoda'): string
    {
        return route($route, ['from' => $from, 'to' => $to]);
    }

    public function test_downloads_xml_with_headers_filtered_and_ordered_by_issue_date(): void
    {
        $this->invoice('issued', '2026-09-20', ['number' => 'FA-B']);
        $this->invoice('paid', '2026-09-05', ['number' => 'FA-A']);
        $this->invoice('overdue', '2026-09-30', ['number' => 'FA-C']);
        $this->invoice('cancelled', '2026-09-10', ['number' => 'FA-D']);
        $this->invoice('issued', '2026-10-01', ['number' => 'FA-OUT']);
        $this->invoice('issued', '2026-08-31', ['number' => 'FA-OUT2']);
        $this->invoice('issued', '2026-09-15', ['number' => 'FA-OTHER'], Tenant::factory()->create());
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'issue_date' => '2026-09-15', 'number' => null]);

        $response = $this->get($this->url());

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=pohoda-2026-09-01-2026-09-30.xml', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(['FA-A', 'FA-D', 'FA-B', 'FA-C'], $this->exportedNumbers((string) $response->getContent()));
    }

    public function test_cancelled_original_and_its_credit_note_are_both_present(): void
    {
        $original = $this->invoice('cancelled', '2026-09-10', ['number' => 'FA-1']);
        $this->invoice('issued', '2026-09-11', ['number' => 'DB-1', 'credited_invoice_id' => $original->id]);

        $xml = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertSame(['FA-1', 'DB-1'], $this->exportedNumbers($xml));
        $this->assertStringContainsString('<inv:invoiceType>issuedCreditNotice</inv:invoiceType>', $xml);
    }

    public function test_empty_range_is_422_because_schema_requires_an_item(): void
    {
        $this->getJson($this->url())->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_user_without_view_invoices_gets_403(): void
    {
        $this->actingAsTenantUser('Interná upratovačka', $this->tenant);

        $this->get($this->url())->assertForbidden();
        $this->get($this->url(route: 'invoices.export.pohoda.preview'))->assertForbidden();
    }

    public function test_to_before_from_is_422(): void
    {
        $this->getJson($this->url('2026-09-30', '2026-09-01'))->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_span_over_366_days_is_422(): void
    {
        $this->getJson($this->url('2025-01-01', '2026-01-03'))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson($this->url('2025-01-01', '2026-01-02', 'invoices.export.pohoda.preview'))->assertOk();
    }

    public function test_missing_dates_are_422(): void
    {
        $this->getJson(route('invoices.export.pohoda'))->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_more_than_max_invoices_is_422(): void
    {
        config(['pohoda.max_invoices' => 2]);
        $this->invoice('issued', '2026-09-01', ['number' => 'A']);
        $this->invoice('issued', '2026-09-02', ['number' => 'B']);

        $this->get($this->url())->assertOk();

        $this->invoice('issued', '2026-09-03', ['number' => 'C']);

        $this->getJson($this->url())->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson($this->url(route: 'invoices.export.pohoda.preview'))->assertUnprocessable();
    }

    public function test_tenant_without_ico_is_422(): void
    {
        $this->tenant->forceFill(['ico' => null])->save();

        $this->getJson($this->url())->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_preview_counts_exported_and_skipped_non_eur(): void
    {
        $this->invoice('issued', '2026-09-05', ['number' => 'A']);
        $this->invoice('issued', '2026-09-06', ['number' => 'B']);
        $this->invoice('issued', '2026-09-07', ['number' => 'C', 'currency' => 'CZK']);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'issue_date' => '2026-09-08']);

        $this->getJson($this->url(route: 'invoices.export.pohoda.preview'))
            ->assertOk()
            ->assertExactJson(['count' => 2, 'skipped_non_eur' => 1]);
    }

    public function test_xml_special_characters_are_escaped(): void
    {
        $invoice = $this->invoice('issued', '2026-09-05', ['number' => 'FA-X', 'customer_name' => 'A & B <C>']);

        $xml = (string) $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('A &amp; B &lt;C&gt;', $xml);
        $this->assertSame([$invoice->number], $this->exportedNumbers($xml));
    }

    public function test_preview_requires_tenant_ico(): void
    {
        $this->tenant->forceFill(['ico' => null])->save();

        $this->getJson($this->url(route: 'invoices.export.pohoda.preview'))->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_unmapped_vat_rate_is_422_on_export_and_preview(): void
    {
        $invoice = $this->invoice('issued', '2026-09-05', ['number' => 'FA-VAT', 'is_vat_payer' => true]);
        $invoice->items()->update(['vat_rate' => 7]);

        foreach (['invoices.export.pohoda', 'invoices.export.pohoda.preview'] as $route) {
            $this->getJson($this->url(route: $route))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('from')
                ->assertJsonPath('errors.from.0', fn (string $m): bool => str_contains($m, 'FA-VAT') && str_contains($m, '7'));
        }
    }

    public function test_non_iso_dates_are_rejected(): void
    {
        $this->getJson(route('invoices.export.pohoda', ['from' => 'tomorrow', 'to' => '2026-09-30']))->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson(route('invoices.export.pohoda', ['from' => '2026-09-01', 'to' => '30.09.2026']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('invoices.export.pohoda', ['from' => '2026-02-30', 'to' => '2026-09-30']))->assertUnprocessable()->assertJsonValidationErrors('from');
    }
}
