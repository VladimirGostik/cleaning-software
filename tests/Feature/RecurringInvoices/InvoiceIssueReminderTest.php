<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Enums\NotificationTypeEnum;
use App\Enums\RecurringBilledMonthEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\InvoiceIssueReminder;
use App\Services\RecurringInvoiceService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvoiceIssueReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    private function member(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        TenantMembership::query()->create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole(Role::inTenant($tenant->id)->where('name', $role)->firstOrFail());

        return $user;
    }

    private function tenantWithRecurring(RecurringBilledMonthEnum $billed = RecurringBilledMonthEnum::Previous, RecurringInvoiceStatusEnum $status = RecurringInvoiceStatusEnum::Active): Tenant
    {
        $tenant = Tenant::factory()->create();
        RoleTemplatesSeeder::seedForTenant($tenant);
        RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'billed_month' => $billed, 'status' => $status]);

        return $tenant;
    }

    private function send(string $date): int
    {
        return app(RecurringInvoiceService::class)->sendIssueReminders(CarbonImmutable::parse($date));
    }

    /** @return array<string, array{string}> */
    public static function reminderDays(): array
    {
        return [
            'november 30th' => ['2026-11-30'],
            'february 28th' => ['2027-02-28'],
            'leap february 29th' => ['2028-02-29'],
        ];
    }

    #[DataProvider('reminderDays')]
    public function test_issuers_get_one_notification_on_the_reminder_day(string $date): void
    {
        Notification::fake();
        $tenant = $this->tenantWithRecurring();
        $admin = $this->member($tenant, RoleTemplatesSeeder::ADMIN_ROLE);

        $this->assertSame(1, $this->send($date));

        Notification::assertSentToTimes($admin, InvoiceIssueReminder::class, 1);
        Notification::assertSentTo($admin, InvoiceIssueReminder::class, function (InvoiceIssueReminder $notification) use ($admin, $date): bool {
            $month = CarbonImmutable::parse($date)->format('n/Y');

            return $notification->month === $month
                && $notification->via($admin) === [TenantDatabaseChannel::class, 'mail']
                && $notification->toArray($admin)['type'] === NotificationTypeEnum::InvoiceIssueReminder->value;
        });
    }

    public function test_other_days_send_nothing(): void
    {
        Notification::fake();
        $tenant = $this->tenantWithRecurring();
        $this->member($tenant, RoleTemplatesSeeder::ADMIN_ROLE);

        $this->assertSame(0, $this->send('2026-11-29'));
        $this->assertSame(0, $this->send('2026-12-31'));
        $this->assertSame(0, $this->send('2027-02-27'));

        Notification::assertNothingSent();
    }

    public function test_firm_without_an_active_dated_recurring_invoice_gets_nothing(): void
    {
        Notification::fake();
        $none = $this->tenantWithRecurring(RecurringBilledMonthEnum::None);
        $paused = $this->tenantWithRecurring(RecurringBilledMonthEnum::Previous, RecurringInvoiceStatusEnum::Paused);
        $this->member($none, RoleTemplatesSeeder::ADMIN_ROLE);
        $this->member($paused, RoleTemplatesSeeder::ADMIN_ROLE);

        $this->assertSame(0, $this->send('2026-11-30'));

        Notification::assertNothingSent();
    }

    public function test_user_without_edit_invoices_gets_nothing(): void
    {
        Notification::fake();
        $tenant = $this->tenantWithRecurring();
        $cleaner = $this->member($tenant, 'Interná upratovačka');

        $this->assertSame(0, $this->send('2026-11-30'));

        Notification::assertNotSentTo($cleaner, InvoiceIssueReminder::class);
    }

    public function test_mail_off_in_preferences_leaves_in_app_only(): void
    {
        $tenant = $this->tenantWithRecurring();
        $admin = $this->member($tenant, RoleTemplatesSeeder::ADMIN_ROLE);
        $admin->update(['notification_preferences' => [NotificationTypeEnum::InvoiceIssueReminder->value => ['mail' => false, 'push' => true]]]);

        $channels = (new InvoiceIssueReminder($tenant->id, '11/2026', $tenant->name))->via($admin);

        $this->assertSame([TenantDatabaseChannel::class], $channels);
    }

    public function test_notification_links_to_the_draft_invoice_list(): void
    {
        $tenant = $this->tenantWithRecurring();
        $admin = $this->member($tenant, RoleTemplatesSeeder::ADMIN_ROLE);

        $payload = (new InvoiceIssueReminder($tenant->id, '11/2026', $tenant->name))->toArray($admin);

        $this->assertIsString($payload['url']);
        $this->assertStringContainsString('filter%5Bstatus%5D=draft', $payload['url']);
        $this->assertSame(['month' => '2026-11'], $payload['meta']);
    }

    public function test_command_runs(): void
    {
        $this->artisanCommand('app:remind-invoice-issue')->assertSuccessful();
    }

    public function test_meta_month_does_not_overflow_when_now_is_the_thirtieth(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-30 12:00'));
        $tenant = $this->tenantWithRecurring();
        $admin = $this->member($tenant, RoleTemplatesSeeder::ADMIN_ROLE);

        $payload = (new InvoiceIssueReminder($tenant->id, '2/2027', $tenant->name))->toArray($admin);

        $this->assertSame(['month' => '2027-02'], $payload['meta']);
    }
}
