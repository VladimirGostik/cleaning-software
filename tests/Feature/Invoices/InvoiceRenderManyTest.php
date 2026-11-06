<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\GeneratesPaymentQr;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Pdf\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Expectation;
use RuntimeException;
use Tests\TestCase;

final class InvoiceRenderManyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    private function pageCount(string $pdf): int
    {
        $path = tempnam(sys_get_temp_dir(), 'inv');
        $this->assertNotFalse($path);
        file_put_contents($path, $pdf);
        $count = (int) trim((string) shell_exec('qpdf --show-npages '.escapeshellarg($path).' 2>/dev/null'));
        unlink($path);

        return $count;
    }

    private function chromeProcesses(): int
    {
        return (int) trim((string) shell_exec('pgrep -fc "chrom.*--headless" 2>/dev/null'));
    }

    public function test_render_many_returns_one_valid_pdf_per_invoice_in_order(): void
    {
        $invoices = [];

        foreach ([1, 2, 3] as $i) {
            $invoices[] = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id, 'number' => '2026000'.$i]);
        }

        $pdfs = app(InvoicePdfService::class)->renderMany([$invoices[2], $invoices[0], $invoices[1]]);

        $this->assertCount(3, $pdfs);

        foreach ($pdfs as $pdf) {
            $this->assertStringStartsWith('%PDF', $pdf);
            $this->assertGreaterThan(5000, strlen($pdf));
            $this->assertSame(1, $this->pageCount($pdf));
        }

        $this->assertSame(3, count(array_unique($pdfs)));
        $this->assertSame(0, $this->chromeProcesses());
    }

    public function test_the_browser_is_closed_when_a_render_fails_mid_batch(): void
    {
        $invoices = [];

        foreach ([1, 2, 3] as $i) {
            $invoices[] = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id]);
        }

        $calls = 0;
        /** @var Expectation $expectation */
        $expectation = $this->mock(GeneratesPaymentQr::class)->shouldReceive('dataUri');
        $expectation->andReturnUsing(function () use (&$calls): ?string {
            if (++$calls === 2) {
                throw new RuntimeException('qr failed');
            }

            return null;
        });

        try {
            app(InvoicePdfService::class)->renderMany($invoices);
            $this->fail('Expected the batch to throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('qr failed', $e->getMessage());
        }

        $this->assertSame(2, $calls);
        $this->assertSame(0, $this->chromeProcesses());
    }
}
