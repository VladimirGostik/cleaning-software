<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SendInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_send_action_queues_job_for_issued_invoice_with_email(): void
    {
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'customer@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        Queue::assertPushed(SendInvoiceEmail::class, function (SendInvoiceEmail $job) use ($invoice): bool {
            return $job->invoiceId === $invoice->id
                && $job->recipientEmail === 'customer@example.com';
        });
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_send_on_draft_invoice_returns_redirect_with_error(): void
    {
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        Queue::assertNotPushed(SendInvoiceEmail::class);
    }

    public function test_send_without_customer_email_returns_redirect_with_error(): void
    {
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => null,
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('customer_email');
        Queue::assertNotPushed(SendInvoiceEmail::class);
    }

    public function test_send_on_cancelled_invoice_returns_redirect_with_error(): void
    {
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->cancelled()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'cancelled@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('status');
        Queue::assertNotPushed(SendInvoiceEmail::class);
    }

    public function test_send_on_overdue_invoice_returns_redirect_with_error(): void
    {
        // Overdue invoices are NOT Issued — send guard requires status === Issued per plan spec
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->overdue()->create([
            'tenant_id' => $tenant->id,
            'customer_email' => 'overdue@example.com',
            'supplier_name' => $tenant->name,
        ]);

        $response = $this->post(route('invoices.send', $invoice));

        $response->assertSessionHasErrors('status');
        Queue::assertNotPushed(SendInvoiceEmail::class);
    }

    public function test_unauthenticated_user_cannot_send(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        // BUG 2: send() must require update/EditInvoices, not view/ViewInvoices.
        // A user with ViewInvoices only must be blocked (403).
        Queue::fake([SendInvoiceEmail::class]);

        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
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
        Queue::assertNotPushed(SendInvoiceEmail::class);
    }
}
