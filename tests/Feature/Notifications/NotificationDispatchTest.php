<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\PermissionEnum;
use App\Events\ContractExpired;
use App\Events\ContractExpiring;
use App\Events\InvoiceMarkedOverdue;
use App\Events\QuoteExpired;
use App\Events\QuoteExpiring;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\ContractExpired as ContractExpiredNotification;
use App\Notifications\ContractExpiring as ContractExpiringNotification;
use App\Notifications\InvoiceOverdue;
use App\Notifications\QuoteExpired as QuoteExpiredNotification;
use App\Notifications\QuoteExpiring as QuoteExpiringNotification;
use App\Notifications\QuoteSent as QuoteSentNotification;
use App\Services\NotificationRecipientResolver;
use App\Services\QuoteService;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeMember(Tenant $tenant, string $role, bool $membershipActive = true, bool $userActive = true): User
    {
        $user = User::factory()->create(['is_active' => $userActive]);
        TenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => $membershipActive,
            'joined_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $roleModel = Role::inTenant($tenant->id)->where('name', $role)->firstOrFail();
        $user->assignRole($roleModel);

        return $user;
    }

    public function test_invoice_marked_overdue_notifies_holders_of_view_invoices_only(): void
    {
        NotificationFacade::fake();
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);

        $admin = $this->makeMember($tenant, RoleTemplatesSeeder::ADMIN_ROLE);
        $accountant = $this->makeMember($tenant, 'Účtovníčka');
        $cleaner = $this->makeMember($tenant, 'Interná upratovačka');
        $inactiveMembership = $this->makeMember($tenant, 'Účtovníčka', membershipActive: false);
        $inactiveUser = $this->makeMember($tenant, 'Účtovníčka', userActive: false);

        $otherTenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($otherTenant);
        $otherTenantAccountant = $this->makeMember($otherTenant, 'Účtovníčka');

        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);

        InvoiceMarkedOverdue::dispatch($tenant->id, $invoice->id);

        NotificationFacade::assertSentTo($admin, InvoiceOverdue::class);
        NotificationFacade::assertSentTo($accountant, InvoiceOverdue::class);
        NotificationFacade::assertNotSentTo($cleaner, InvoiceOverdue::class);
        NotificationFacade::assertNotSentTo($inactiveMembership, InvoiceOverdue::class);
        NotificationFacade::assertNotSentTo($inactiveUser, InvoiceOverdue::class);
        NotificationFacade::assertNotSentTo($otherTenantAccountant, InvoiceOverdue::class);
    }

    public function test_listener_with_zero_recipients_sends_nothing_and_does_not_throw(): void
    {
        NotificationFacade::fake();
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        $invoice = Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);

        InvoiceMarkedOverdue::dispatch($tenant->id, $invoice->id);

        NotificationFacade::assertNothingSent();
    }

    public function test_contract_expired_and_expiring_notify_view_contracts_holders(): void
    {
        NotificationFacade::fake();
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        $secretary = $this->makeMember($tenant, 'Sekretárka');
        $cleaner = $this->makeMember($tenant, 'Interná upratovačka');
        $this->bindTenant($tenant);
        $contract = Contract::factory()->active()->create(['tenant_id' => $tenant->id]);

        ContractExpired::dispatch($tenant->id, $contract->id);
        ContractExpiring::dispatch($tenant->id, $contract->id, 30);

        NotificationFacade::assertSentTo($secretary, ContractExpiredNotification::class);
        NotificationFacade::assertSentTo(
            $secretary,
            ContractExpiringNotification::class,
            fn (ContractExpiringNotification $n): bool => $n->daysLeft === 30,
        );
        NotificationFacade::assertNotSentTo($cleaner, ContractExpiredNotification::class);
    }

    public function test_quote_sent_expiring_expired_notify_view_quotes_holders(): void
    {
        NotificationFacade::fake();
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        $secretary = $this->makeMember($tenant, 'Sekretárka');
        $quote = Quote::factory()->numbered()->create(['tenant_id' => $tenant->id]);

        app(QuoteService::class)->send($quote);
        QuoteExpiring::dispatch($tenant->id, $quote->id, 7);
        QuoteExpired::dispatch($tenant->id, $quote->id);

        NotificationFacade::assertSentTo($secretary, QuoteSentNotification::class);
        NotificationFacade::assertSentTo(
            $secretary,
            QuoteExpiringNotification::class,
            fn (QuoteExpiringNotification $n): bool => $n->daysLeft === 7,
        );
        NotificationFacade::assertSentTo($secretary, QuoteExpiredNotification::class);
    }

    public function test_resolver_restores_previous_team_id_when_previously_null(): void
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        app(NotificationRecipientResolver::class)->usersWithPermission($tenant->id, PermissionEnum::ViewInvoices);

        $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_resolver_restores_previous_team_id_when_previously_bound(): void
    {
        $callerTenant = Tenant::factory()->create();
        $targetTenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($targetTenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($callerTenant->id);

        app(NotificationRecipientResolver::class)->usersWithPermission($targetTenant->id, PermissionEnum::ViewInvoices);

        $this->assertSame($callerTenant->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }
}
