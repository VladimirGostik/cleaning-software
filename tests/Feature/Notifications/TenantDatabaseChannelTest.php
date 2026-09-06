<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\InvoiceOverdue;
use App\Notifications\QuoteSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantDatabaseChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_writes_row_with_tenant_id_type_and_data_shape(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        $invoiceNumber = $invoice->number ?? __('app.invoice_pdf_draft');

        $user->notify(new InvoiceOverdue($tenant->id, $invoice->id, $invoiceNumber));

        $row = $user->notifications()->firstOrFail();
        $this->assertSame($tenant->id, $row->tenant_id);
        $this->assertSame('invoice.overdue', $row->type);
        $this->assertSame($user->id, $row->notifiable_id);
        $this->assertArrayHasKey('title', $row->data);
        $this->assertArrayHasKey('body', $row->data);
        $this->assertArrayHasKey('url', $row->data);
        $this->assertArrayHasKey('meta', $row->data);
        $title = $row->data['title'];
        $this->assertTrue(is_string($title) && str_contains($title, $invoiceNumber));
        $this->assertNull($row->read_at);
    }

    public function test_via_excludes_mail_when_preference_disabled(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['notification_preferences' => ['invoice.overdue' => ['mail' => false]]]);
        $notification = new InvoiceOverdue($tenant->id, 'invoice-id', 'FA-2026-0001');

        $this->assertSame([TenantDatabaseChannel::class], $notification->via($user));
    }

    public function test_via_includes_mail_when_preference_enabled_or_default_true(): void
    {
        $tenant = Tenant::factory()->create();
        $userExplicit = User::factory()->create(['notification_preferences' => ['invoice.overdue' => ['mail' => true]]]);
        $userDefault = User::factory()->create(['notification_preferences' => []]);
        $notification = new InvoiceOverdue($tenant->id, 'invoice-id', 'FA-2026-0001');

        $this->assertSame([TenantDatabaseChannel::class, 'mail'], $notification->via($userExplicit));
        $this->assertSame([TenantDatabaseChannel::class, 'mail'], $notification->via($userDefault));
    }

    public function test_via_excludes_mail_by_default_for_quote_sent(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['notification_preferences' => []]);
        $notification = new QuoteSent($tenant->id, 'quote-id', 'CP-2026-0001');

        $this->assertSame([TenantDatabaseChannel::class], $notification->via($user));
    }

    public function test_notification_title_follows_recipient_locale(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['locale' => 'en']);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        $invoiceNumber = $invoice->number ?? __('app.invoice_pdf_draft');

        $user->notify(new InvoiceOverdue($tenant->id, $invoice->id, $invoiceNumber));

        $row = $user->notifications()->firstOrFail();
        $this->assertSame(__('app.notification_invoice_overdue_title', ['number' => $invoiceNumber], 'en'), $row->data['title']);
    }
}
