<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SendInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_send_action_dispatches_notification_for_issued_invoice_with_email(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
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
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_send_on_draft_invoice_returns_redirect_with_error(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
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

    public function test_send_without_customer_email_returns_redirect_with_error(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
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

    public function test_send_on_cancelled_invoice_returns_redirect_with_error(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'cancelled@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('status');
        Notification::assertNothingSent();
    }

    public function test_send_on_overdue_invoice_returns_redirect_with_error(): void
    {
        // Overdue invoices are NOT Issued — send guard requires status === Issued per plan spec
        Notification::fake();

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->overdue()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'overdue@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('status');
        Notification::assertNothingSent();
    }

    public function test_unauthenticated_user_cannot_send(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'test@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $this->post(route('logout'));

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_edit_invoices_permission_cannot_send(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Interná upratovačka');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Give this user ViewInvoices but NOT EditInvoices — simulates view-only role
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->givePermissionTo(PermissionEnum::ViewInvoices->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'test@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertForbidden();
        Notification::assertNothingSent();
    }
}
