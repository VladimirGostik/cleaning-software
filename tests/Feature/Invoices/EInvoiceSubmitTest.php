<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\SubmitsEInvoices;
use App\Enums\EfaStatusEnum;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Tenant;
use App\Services\Efa\EInvoiceService;
use App\Services\Efa\EInvoiceSubmissionResult;
use App\Services\InvoiceService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\BuildsEfaInvoices;
use Tests\Support\CreatesUsers;
use Tests\Support\FakeEInvoiceAccessPoint;
use Tests\TestCase;

final class EInvoiceSubmitTest extends TestCase
{
    use BuildsEfaInvoices, CreatesUsers, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(array $attributes = []): Invoice
    {
        return $this->efaInvoice($this->tenant, $attributes, [
            ['description' => 'Upratovanie', 'quantity' => 1, 'unit' => 'hod', 'unit_price' => 50, 'line_base' => 50, 'line_vat' => 0, 'line_total' => 50],
        ]);
    }

    /** @param Closure(Invoice, string): EInvoiceSubmissionResult $handler */
    private function provider(Closure $handler): FakeEInvoiceAccessPoint
    {
        $fake = new FakeEInvoiceAccessPoint($handler);
        $this->app->instance(SubmitsEInvoices::class, $fake);

        return $fake;
    }

    public function test_null_driver_returns_not_configured_and_leaves_state_untouched(): void
    {
        $invoice = $this->invoice();

        $response = $this->post(route('invoices.efa.submit', $invoice));

        $response->assertSessionHasErrors(['efa' => __('app.efa_not_configured')]);
        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::None, $invoice->efa_status);
        $this->assertNull($invoice->efa_submitted_at);
        $this->assertCount(0, $invoice->getMedia('efa_xml'));
    }

    public function test_accepted_result_stores_xml_and_persists_status_and_logs_activity(): void
    {
        $captured = null;
        $this->provider(function (Invoice $invoice, string $xml) use (&$captured): EInvoiceSubmissionResult {
            $captured = $xml;

            return new EInvoiceSubmissionResult(true, 'PROV-42', EfaStatusEnum::Accepted);
        });
        $invoice = $this->invoice();

        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::Accepted, $invoice->efa_status);
        $this->assertSame('PROV-42', $invoice->efa_id);
        $this->assertNotNull($invoice->efa_submitted_at);
        $this->assertNull($invoice->efa_last_error);
        $media = $invoice->getMedia('efa_xml');
        $this->assertCount(1, $media);
        $stored = $media->firstOrFail();
        $this->assertSame($invoice->number.'.xml', $stored->file_name);
        $this->assertSame($captured, file_get_contents($stored->getPath()));
        $this->assertTrue(Activity::query()
            ->where('subject_id', $invoice->id)
            ->where('attribute_changes->attributes->efa_status', 'accepted')
            ->exists());
    }

    public function test_non_validation_throwable_marks_failed_without_media(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => throw new RuntimeException('Gateway timeout secret-host:8443', 503));
        $invoice = $this->invoice();

        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::Failed, $invoice->efa_status);
        $this->assertSame(__('app.efa_submit_failed').' (503)', $invoice->efa_last_error);
        $this->assertStringNotContainsString('Gateway timeout', (string) $invoice->efa_last_error);
        $this->assertCount(0, $invoice->getMedia('efa_xml'));
    }

    public function test_failed_invoice_can_be_resubmitted_and_clears_error(): void
    {
        $invoice = $this->invoice(['efa_status' => EfaStatusEnum::Failed, 'efa_last_error' => 'old']);
        $this->provider(fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(true, 'P-1', EfaStatusEnum::Submitted));

        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::Submitted, $invoice->efa_status);
        $this->assertNull($invoice->efa_last_error);
    }

    public function test_rejected_then_resubmitted_stores_second_media_item(): void
    {
        $fake = $this->provider(fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(false, 'P-1', EfaStatusEnum::Rejected, 'Bad endpoint'));
        $invoice = $this->invoice();

        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect();
        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::Rejected, $invoice->efa_status);
        $this->assertSame('Bad endpoint', $invoice->efa_last_error);
        $this->assertCount(1, $invoice->getMedia('efa_xml'));

        $fake->handler = fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(true, 'P-2', EfaStatusEnum::Accepted);
        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertSame(EfaStatusEnum::Accepted, $invoice->efa_status);
        $this->assertCount(2, $invoice->getMedia('efa_xml'));
    }

    public function test_already_submitted_or_accepted_is_rejected(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => throw new RuntimeException('must not be called'));

        foreach ([EfaStatusEnum::Submitted, EfaStatusEnum::Accepted] as $status) {
            $invoice = $this->invoice(['efa_status' => $status]);

            $this->post(route('invoices.efa.submit', $invoice))->assertSessionHasErrors(['efa' => __('app.efa_already_submitted')]);
            $this->assertSame($status, $invoice->refresh()->efa_status);
        }
    }

    public function test_draft_and_not_ready_invoices_are_rejected_without_calling_provider(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => throw new RuntimeException('must not be called'));
        $draft = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $incomplete = $this->invoice(['supplier_ico' => null]);

        $this->post(route('invoices.efa.submit', $draft))->assertSessionHasErrors('status');
        $this->post(route('invoices.efa.submit', $incomplete))->assertSessionHasErrors('efa');
        $this->assertSame(EfaStatusEnum::None, $incomplete->refresh()->efa_status);
    }

    public function test_view_only_user_cannot_submit(): void
    {
        $this->actingAs($this->userWithPermission('view invoices'));
        $invoice = $this->efaInvoice(Tenant::query()->findOrFail(current_tenant_id()), [], [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 10, 'line_base' => 10, 'line_vat' => 0, 'line_total' => 10],
        ]);

        $this->post(route('invoices.efa.submit', $invoice))->assertForbidden();
    }

    public function test_cross_tenant_invoice_is_not_found(): void
    {
        $foreign = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->post(route('invoices.efa.submit', $foreign))->assertNotFound();
    }

    public function test_stale_model_cannot_double_submit(): void
    {
        $calls = 0;
        $this->provider(function () use (&$calls): EInvoiceSubmissionResult {
            $calls++;

            return new EInvoiceSubmissionResult(true, 'P-1', EfaStatusEnum::Submitted);
        });
        $invoice = $this->invoice();
        $stale = Invoice::query()->findOrFail($invoice->id);
        $this->assertSame(EfaStatusEnum::None, $stale->efa_status);

        app(EInvoiceService::class)->submit($invoice);

        try {
            app(EInvoiceService::class)->submit($stale);
            $this->fail('Second submit must be rejected');
        } catch (ValidationException $e) {
            $this->assertSame([__('app.efa_already_submitted')], $e->errors()['efa']);
        }

        $this->assertSame(1, $calls);
        $this->assertCount(1, $invoice->refresh()->getMedia('efa_xml'));
    }

    public function test_provider_validation_exception_restores_previous_status(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => throw ValidationException::withMessages(['efa' => ['nope']]));
        $invoice = $this->invoice(['efa_status' => EfaStatusEnum::Rejected]);

        $this->post(route('invoices.efa.submit', $invoice))->assertSessionHasErrors('efa');

        $this->assertSame(EfaStatusEnum::Rejected, $invoice->refresh()->efa_status);
    }

    public function test_provider_message_is_truncated_to_255(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(false, null, EfaStatusEnum::Rejected, str_repeat('x', 400)));
        $invoice = $this->invoice();

        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect();

        $this->assertSame(255, strlen((string) $invoice->refresh()->efa_last_error));
    }

    public function test_soft_deleting_invoice_keeps_efa_media(): void
    {
        $this->provider(fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(true, 'P-1', EfaStatusEnum::Accepted));
        $invoice = $this->invoice();
        $this->post(route('invoices.efa.submit', $invoice))->assertRedirect();

        $invoice->refresh()->delete();

        $this->assertSame(1, Media::query()->where('model_id', $invoice->id)->where('collection_name', 'efa_xml')->count());
        $this->assertTrue(Invoice::withTrashed()->findOrFail($invoice->id)->trashed());
    }

    public function test_cancel_copies_peppol_snapshots_onto_credit_note(): void
    {
        $invoice = $this->invoice(['supplier_peppol_participant_id' => '0245:12345678', 'customer_peppol_participant_id' => '9950:SK1']);

        app(InvoiceService::class)->cancel($invoice, null);

        $creditNote = $invoice->refresh()->creditNote;
        $this->assertNotNull($creditNote);
        $this->assertSame('0245:12345678', $creditNote->supplier_peppol_participant_id);
        $this->assertSame('9950:SK1', $creditNote->customer_peppol_participant_id);
    }

    public function test_status_can_submit_requires_real_driver_and_readiness(): void
    {
        $invoice = $this->invoice();
        $this->assertFalse(app(EInvoiceService::class)->status($invoice)->can_submit);

        $this->provider(fn (): EInvoiceSubmissionResult => new EInvoiceSubmissionResult(true, null, EfaStatusEnum::Accepted));
        $this->assertTrue(app(EInvoiceService::class)->status($invoice)->can_submit);

        $invoice->update(['efa_status' => EfaStatusEnum::Accepted]);
        $this->assertFalse(app(EInvoiceService::class)->status($invoice)->can_submit);

        $incomplete = $this->invoice(['supplier_ico' => null]);
        $this->assertFalse(app(EInvoiceService::class)->status($incomplete)->can_submit);

        $draft = Invoice::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertFalse(app(EInvoiceService::class)->status($draft)->can_submit);
    }
}
