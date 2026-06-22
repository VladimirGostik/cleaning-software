<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Console\Commands\CheckContractExpiry;
use App\Console\Commands\ExpireQuotes;
use App\Console\Commands\MarkOverdueInvoices;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\ContractExpired;
use App\Notifications\ContractExpiring;
use App\Notifications\InvoiceOverdue;
use App\Notifications\QuoteExpired;
use App\Notifications\QuoteExpiring;
use App\Notifications\QuoteSent;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class SystemNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Mark overdue invoices cron
    // -------------------------------------------------------------------------

    public function test_overdue_invoice_notifies_view_invoices_users(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Účtovníčka has ViewInvoices
        $accountant = User::factory()->create();
        TenantMembership::create([
            'user_id' => $accountant->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $accountant->givePermissionTo(PermissionEnum::ViewInvoices->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // User WITHOUT ViewInvoices (Upratovačka)
        $cleaner = User::factory()->create();
        TenantMembership::create([
            'user_id' => $cleaner->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued,
            'due_date' => now()->subDay()->toDateString(),
            'supplier_name' => $tenant->name,
        ]);

        Artisan::call(MarkOverdueInvoices::class);

        Notification::assertSentTo($user, InvoiceOverdue::class);
        Notification::assertSentTo($accountant, InvoiceOverdue::class);
        Notification::assertNotSentTo($cleaner, InvoiceOverdue::class);
    }

    public function test_overdue_invoice_resolver_excludes_inactive_members(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $inactive = User::factory()->create();
        TenantMembership::create([
            'user_id' => $inactive->id,
            'tenant_id' => $tenant->id,
            'is_active' => false, // inactive
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $inactive->givePermissionTo(PermissionEnum::ViewInvoices->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued,
            'due_date' => now()->subDay()->toDateString(),
            'supplier_name' => $tenant->name,
        ]);

        Artisan::call(MarkOverdueInvoices::class);

        Notification::assertNotSentTo($inactive, InvoiceOverdue::class);
    }

    // -------------------------------------------------------------------------
    // Contract expiry cron
    // -------------------------------------------------------------------------

    public function test_expired_contract_notifies_view_contracts_users(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'status' => ContractStatusEnum::Active,
            'term_type' => ContractTermTypeEnum::Fixed,
            'end_date' => now()->subDay()->toDateString(),
            'valid_from' => now()->subYear()->toDateString(),
        ]);

        Artisan::call(CheckContractExpiry::class);

        Notification::assertSentTo($user, ContractExpired::class);
    }

    public function test_expiring_contract_dispatches_contract_expiring_notification(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        Contract::factory()->create([
            'tenant_id' => $tenant->id,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'status' => ContractStatusEnum::Active,
            'term_type' => ContractTermTypeEnum::Fixed,
            'end_date' => now()->addDays(7)->toDateString(),
            'valid_from' => now()->subYear()->toDateString(),
        ]);

        Artisan::call(CheckContractExpiry::class);

        Notification::assertSentTo($user, ContractExpiring::class);
    }

    // -------------------------------------------------------------------------
    // Quote expiry cron
    // -------------------------------------------------------------------------

    public function test_expired_quote_dispatches_quote_expired_notification(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => QuoteStatusEnum::Sent,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        Artisan::call(ExpireQuotes::class);

        Notification::assertSentTo($user, QuoteExpired::class);
    }

    public function test_expiring_quote_dispatches_quote_expiring_notification(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => QuoteStatusEnum::Sent,
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        Artisan::call(ExpireQuotes::class);

        Notification::assertSentTo($user, QuoteExpiring::class);
    }

    // -------------------------------------------------------------------------
    // QuoteService::send
    // -------------------------------------------------------------------------

    public function test_quote_send_dispatches_quote_sent_to_view_quotes_users(): void
    {
        Notification::fake();

        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => QuoteStatusEnum::Draft,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        app(QuoteService::class)->send($quote);

        Notification::assertSentTo($user, QuoteSent::class);
    }

    // -------------------------------------------------------------------------
    // TenantDatabaseChannel writes tenant_id
    // -------------------------------------------------------------------------

    public function test_tenant_database_channel_writes_tenant_id_column(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // Real send (no Notification::fake) to assert DB row
        $notification = new InvoiceOverdue($tenant->id, 'fake-invoice-id');
        $user->notify($notification);

        $row = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals($tenant->id, $row->tenant_id);
    }
}
