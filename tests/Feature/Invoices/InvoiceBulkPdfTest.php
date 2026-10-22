<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\MergesPdfDocuments;
use App\Contracts\RendersInvoicePdf;
use App\Exceptions\PdfMergeFailedException;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\Expectation;
use Tests\TestCase;
use Throwable;

final class InvoiceBulkPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    /** @return list<Invoice> */
    private function invoices(int $count, string $state = 'issued'): array
    {
        $invoices = [];

        for ($i = 0; $i < $count; $i++) {
            $factory = $state === 'draft' ? Invoice::factory() : Invoice::factory()->issued();
            $invoices[] = $factory->create(['tenant_id' => $this->tenant->id]);
        }

        return $invoices;
    }

    private function mockRenderer(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->andReturnUsing(fn (Invoice $invoice): string => 'PDF:'.$invoice->id);
    }

    /** @param  list<string>|null  $with */
    private function mockMerger(?int $times, ?array $with = null, string|Throwable $returns = 'merged'): void
    {
        if ($times === 0) {
            $this->mock(MergesPdfDocuments::class)->shouldNotReceive('merge');

            return;
        }

        /** @var Expectation $expectation */
        $expectation = $this->mock(MergesPdfDocuments::class)->shouldReceive('merge');
        $expectation->once();

        if ($with !== null) {
            $expectation->with($with);
        }

        $returns instanceof Throwable ? $expectation->andThrow($returns) : $expectation->andReturn($returns);
    }

    public function test_three_ids_render_three_times_and_merge_once_in_requested_order(): void
    {
        [$a, $b, $c] = $this->invoices(3);
        $this->mockRenderer();
        $this->mockMerger(1, ['PDF:'.$c->id, 'PDF:'.$a->id, 'PDF:'.$b->id], '%PDF-merged');

        $response = $this->get(route('invoices.bulk.pdf', ['ids' => [$c->id, $a->id, $b->id]]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('faktury-'.now()->toDateString().'.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-merged', $response->getContent());
    }

    public function test_single_id_skips_merge(): void
    {
        [$a] = $this->invoices(1);
        $this->mockRenderer();
        $this->mockMerger(0);

        $this->get(route('invoices.bulk.pdf', ['ids' => [$a->id]]))->assertOk()->assertSee('PDF:'.$a->id, false);
    }

    public function test_draft_invoices_are_allowed(): void
    {
        [$a, $b] = $this->invoices(2, 'draft');
        $this->mockRenderer();
        $this->mockMerger(1);

        $this->get(route('invoices.bulk.pdf', ['ids' => [$a->id, $b->id]]))->assertOk();
    }

    public function test_more_than_cap_is_rejected(): void
    {
        $this->mock(RendersInvoicePdf::class)->shouldNotReceive('render');
        $ids = array_map(fn (): string => (string) fake()->uuid(), range(1, config()->integer('invoicing.bulk.pdf_max_ids') + 1));

        $this->get(route('invoices.bulk.pdf', ['ids' => $ids]))->assertSessionHasErrors('ids');
    }

    public function test_empty_non_uuid_and_duplicate_ids_are_rejected(): void
    {
        [$a] = $this->invoices(1);

        $this->get(route('invoices.bulk.pdf'))->assertSessionHasErrors('ids');
        $this->get(route('invoices.bulk.pdf', ['ids' => ['x']]))->assertSessionHasErrors('ids.0');
        $this->get(route('invoices.bulk.pdf', ['ids' => [$a->id, $a->id]]))->assertSessionHasErrors();
    }

    public function test_foreign_tenant_id_returns_404(): void
    {
        [$a] = $this->invoices(1);
        $other = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->mock(RendersInvoicePdf::class)->shouldNotReceive('render');

        $this->get(route('invoices.bulk.pdf', ['ids' => [$a->id, $other->id]]))->assertNotFound();
    }

    public function test_merge_failure_redirects_back_with_error_and_serves_no_file(): void
    {
        [$a, $b] = $this->invoices(2);
        $this->mockRenderer();
        $this->mockMerger(1, null, new PdfMergeFailedException('boom', 2, 'stderr text'));

        $this->from(route('invoices.index'))
            ->get(route('invoices.bulk.pdf', ['ids' => [$a->id, $b->id]]))
            ->assertRedirect(route('invoices.index'))
            ->assertSessionHas('error', __('app.invoice_bulk_pdf_failed'));
    }
}
