<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class InvoiceIssuedNotificationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function test_send_action_dispatches_invoice_issued_notification_to_customer_email(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'customer@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        Notification::assertSentOnDemand(
            InvoiceIssued::class,
            fn (InvoiceIssued $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'customer@example.com'
                && $notification->invoiceId === $invoice->id,
        );
    }

    // -------------------------------------------------------------------------
    // StampInvoiceSentAt listener
    // -------------------------------------------------------------------------

    public function test_stamp_invoice_sent_at_sets_sent_at_when_notification_sent_event_fires(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'customer@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $this->assertNull($invoice->sent_at);

        // Fire NotificationSent directly — avoids PDF rendering in test environment
        event(new NotificationSent(
            notifiable: new AnonymousNotifiable,
            notification: new InvoiceIssued($invoice->id),
            channel: 'mail',
        ));

        $invoice->refresh();
        $this->assertNotNull($invoice->sent_at);
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_send_on_draft_invoice_returns_session_error_on_status(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Draft,
            'customer_email' => 'draft@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('status');
        Notification::assertNothingSent();
    }

    public function test_send_without_customer_email_returns_session_error_on_customer_email(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => null,
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('customer_email');
        Notification::assertNothingSent();
    }
}
