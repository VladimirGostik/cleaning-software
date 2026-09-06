<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Mockery\Expectation;
use Tests\TestCase;

final class InvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_send_queues_invoice_issued_notification_to_customer_email(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        $this->post(route('invoices.send', $invoice))->assertRedirect(route('invoices.show', $invoice));

        Notification::assertSentOnDemand(
            InvoiceIssued::class,
            fn (InvoiceIssued $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'client@example.com'
                && $notification->invoiceId === $invoice->id,
        );
    }

    public function test_notification_sent_event_stamps_sent_at(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');

        app(InvoiceService::class)->send($invoice);
        $invoice->refresh();

        $this->assertNotNull($invoice->sent_at);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_send_draft_invoice_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('status');
    }

    public function test_send_overdue_invoice_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('status');
    }

    public function test_send_cancelled_invoice_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('status');
    }

    public function test_send_missing_customer_email_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => null]);

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('customer_email');
    }

    public function test_send_forbidden_without_edit_invoices_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => 'client@example.com']);

        $this->post(route('invoices.send', $invoice))->assertForbidden();
    }
}
