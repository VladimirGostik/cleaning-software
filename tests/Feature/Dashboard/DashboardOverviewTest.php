<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Enums\CurrencyEnum;
use App\Enums\DashboardAlertTypeEnum;
use App\Enums\JobStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * @phpstan-type OverviewCompanyShape array{
 *     tenant_id: string,
 *     name: string,
 *     color: string|null,
 *     is_current: bool,
 *     supplier_complete: bool,
 *     invoices: array{default_currency: string, by_currency: list<array{currency: string, invoiced_month_count: int, invoiced_month_sum: string, unpaid_count: int, unpaid_sum: string, overdue_count: int, overdue_sum: string}>}|null,
 *     schedule: array{today: int, this_week: int, unassigned_next_7_days: int|null, own_only: bool}|null,
 *     contracts: array{active: int|null, expiring_30d: int|null, quotes_awaiting: int|null}|null,
 *     people: array{employees: int|null, clients: int|null, objects: int|null}|null,
 * }
 * @phpstan-type OverviewAlertShape array{
 *     type: string,
 *     tenant_id: string,
 *     tenant_name: string,
 *     tenant_color: string|null,
 *     title: string,
 *     subtitle: string|null,
 *     amount: string|null,
 *     currency: string|null,
 *     due_date: string|null,
 *     days: int|null,
 *     url: string,
 * }
 * @phpstan-type OverviewShape array{
 *     companies: list<OverviewCompanyShape>,
 *     alerts: list<OverviewAlertShape>,
 *     alert_counts: list<array{type: string, count: int}>,
 *     generated_at: string,
 * }
 */
final class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-16 10:00:00'));
    }

    public function test_shape_for_single_tenant_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $overview = $this->getOverview($response);

        $this->assertCount(1, $overview['companies']);
        $company = $overview['companies'][0];
        $this->assertSame($tenant->id, $company['tenant_id']);
        $this->assertTrue($company['is_current']);
        $this->assertNotNull($company['invoices']);
        $this->assertNotNull($company['schedule']);
        $this->assertNotNull($company['contracts']);
        $this->assertNotNull($company['people']);
        $this->assertCount(5, $overview['alert_counts']);
        $this->assertNotEmpty($overview['generated_at']);
    }

    public function test_multi_company_ordering_excludes_inactive_and_non_member_tenants(): void
    {
        $tenantB = Tenant::factory()->create(['name' => 'B Company']);
        $user = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenantB);

        $tenantA = Tenant::factory()->create(['name' => 'A Company']);
        $this->addMembership($user, $tenantA, RoleTemplatesSeeder::ADMIN_ROLE);

        $tenantC = Tenant::factory()->create(['name' => 'C Company']);
        $this->addMembership($user, $tenantC, RoleTemplatesSeeder::ADMIN_ROLE, active: false);

        $tenantD = Tenant::factory()->create(['name' => 'D Company']);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $overview = $this->getOverview($response);

        $ids = array_column($overview['companies'], 'tenant_id');
        $this->assertSame([$tenantA->id, $tenantB->id], $ids);

        $alertTenantIds = array_unique(array_column($overview['alerts'], 'tenant_id'));
        $this->assertNotContains($tenantC->id, $alertTenantIds);
        $this->assertNotContains($tenantD->id, $alertTenantIds);
    }

    public function test_cross_tenant_isolation_for_overdue_invoice_and_unassigned_job(): void
    {
        $tenantA = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenantA);

        $tenantD = Tenant::factory()->create();
        $clientD = Client::factory()->create(['tenant_id' => $tenantD->id]);
        $objectD = CleaningObject::factory()->create(['tenant_id' => $tenantD->id, 'client_id' => $clientD->id]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenantD->id]);
        ScheduledJob::factory()->forObject($objectD)->create([
            'tenant_id' => $tenantD->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => today()->addDay()->toDateString(),
        ]);

        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $objectA = CleaningObject::factory()->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenantA->id]);
        ScheduledJob::factory()->forObject($objectA)->create([
            'tenant_id' => $tenantA->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => today()->addDay()->toDateString(),
        ]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $this->assertCount(1, $overview['companies']);
        $this->assertSame($tenantA->id, $overview['companies'][0]['tenant_id']);

        $alertTenantIds = array_unique(array_column($overview['alerts'], 'tenant_id'));
        $this->assertNotContains($tenantD->id, $alertTenantIds);
        $this->assertContains($tenantA->id, $alertTenantIds);
    }

    public function test_per_tenant_permission_gating_nulls_missing_metric_groups(): void
    {
        $tenantA = Tenant::factory()->create();
        $user = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenantA);

        $tenantB = Tenant::factory()->create();
        $this->addMembershipWithPermissions($user, $tenantB, [
            PermissionEnum::ViewInvoices->value,
            PermissionEnum::ViewContracts->value,
            PermissionEnum::ViewClients->value,
        ]);

        Invoice::factory()->overdue()->create(['tenant_id' => $tenantB->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);
        ScheduledJob::factory()->forObject($objectB)->create([
            'tenant_id' => $tenantB->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => today()->addDay()->toDateString(),
        ]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $b = collect($overview['companies'])->firstWhere('tenant_id', $tenantB->id);
        $this->assertNotNull($b);
        $this->assertNotNull($b['invoices']);
        $this->assertNull($b['schedule']);
        $this->assertNotNull($b['contracts']);
        $this->assertIsInt($b['contracts']['active']);
        $this->assertNull($b['contracts']['quotes_awaiting']);
        $this->assertNotNull($b['people']);
        $this->assertNull($b['people']['employees']);
        $this->assertIsInt($b['people']['clients']);
        $this->assertNull($b['people']['objects']);

        $a = collect($overview['companies'])->firstWhere('tenant_id', $tenantA->id);
        $this->assertNotNull($a);
        $this->assertNotNull($a['invoices']);
        $this->assertNotNull($a['schedule']);
        $this->assertNotNull($a['contracts']);
        $this->assertNotNull($a['people']);

        $bAlertTypes = collect($overview['alerts'])->where('tenant_id', $tenantB->id)->pluck('type');
        $this->assertContains(DashboardAlertTypeEnum::OverdueInvoice->value, $bAlertTypes);
        $this->assertNotContains(DashboardAlertTypeEnum::UnassignedJob->value, $bAlertTypes);
    }

    public function test_fail_closed_never_returns_zero_for_missing_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Vedúca', $tenant);

        Invoice::factory()->overdue()->count(2)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $this->assertNull($overview['companies'][0]['invoices']);

        $alertTypes = collect($overview['alerts'])->pluck('type');
        $this->assertNotContains(DashboardAlertTypeEnum::OverdueInvoice->value, $alertTypes);

        $overdueCount = collect($overview['alert_counts'])->firstWhere('type', DashboardAlertTypeEnum::OverdueInvoice->value);
        $this->assertNotNull($overdueCount);
        $this->assertSame(0, $overdueCount['count']);
    }

    public function test_registrar_and_shared_props_reflect_active_tenant_after_dashboard_load(): void
    {
        $tenantA = Tenant::factory()->create();
        $user = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenantA);

        $tenantB = Tenant::factory()->create();
        $this->addMembership($user, $tenantB, 'Účtovníčka');

        $response = $this->get(route('dashboard'));

        $response->assertOk();

        /** @var array<string, bool> $can */
        $can = $response->inertiaProps('can');
        $this->assertTrue($can['viewSchedule'] ?? false);

        /** @var array{active: array{id: string}|null, available: mixed} $tenantProp */
        $tenantProp = $response->inertiaProps('tenant');
        $this->assertSame($tenantA->id, $tenantProp['active']['id'] ?? null);
    }

    public function test_invoice_metrics_correctness_for_admin_eur_default(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);
        $today = today()->toDateString();

        // Draft invoice doubles as the FK anchor for the credit note below — draft status
        // already excludes it from every aggregate, so it is a "free" anchor.
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'total' => '999.00']);
        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'total' => '100.00']);
        Invoice::factory()->paid()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'total' => '50.00']);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'total' => '30.00']);
        Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'total' => '10.00']);
        Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'issue_date' => $today,
            'total' => '100.00',
            'credited_invoice_id' => $draft->id,
        ]);
        Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'issue_date' => $today,
            'total' => '200.00',
            'currency' => CurrencyEnum::CZK,
        ]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $invoices = $overview['companies'][0]['invoices'];
        $this->assertNotNull($invoices);
        $byCurrency = $invoices['by_currency'];

        $this->assertSame('EUR', $byCurrency[0]['currency']);
        $this->assertSame(3, $byCurrency[0]['invoiced_month_count']);
        $this->assertSame('180.00', $byCurrency[0]['invoiced_month_sum']);
        $this->assertSame(1, $byCurrency[0]['unpaid_count']);
        $this->assertSame('100.00', $byCurrency[0]['unpaid_sum']);
        $this->assertSame(1, $byCurrency[0]['overdue_count']);
        $this->assertSame('30.00', $byCurrency[0]['overdue_sum']);

        $this->assertSame('CZK', $byCurrency[1]['currency']);
        $this->assertSame(1, $byCurrency[1]['invoiced_month_count']);
        $this->assertSame('200.00', $byCurrency[1]['invoiced_month_sum']);
        $this->assertSame(1, $byCurrency[1]['unpaid_count']);
        $this->assertSame('200.00', $byCurrency[1]['unpaid_sum']);
    }

    public function test_non_default_currency_row_only_surfaces_when_non_zero(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);
        $today = today()->toDateString();

        // Draft CZK invoice groups by currency in the base query but matches no status FILTER —
        // must not leak an all-zero CZK row.
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'currency' => CurrencyEnum::CZK, 'total' => '999.00']);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $invoices = $overview['companies'][0]['invoices'];
        $this->assertNotNull($invoices);
        $byCurrency = $invoices['by_currency'];

        $this->assertCount(1, $byCurrency);
        $this->assertSame('EUR', $byCurrency[0]['currency']);

        // Issuing the CZK invoice now makes it match the "invoiced this month" FILTER -> row appears.
        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'issue_date' => $today, 'currency' => CurrencyEnum::CZK, 'total' => '150.00']);

        $overview = $this->getOverview($this->get(route('dashboard')));
        $invoices = $overview['companies'][0]['invoices'];
        $this->assertNotNull($invoices);
        $byCurrency = $invoices['by_currency'];

        $this->assertCount(2, $byCurrency);
        $this->assertSame('CZK', $byCurrency[1]['currency']);
        $this->assertSame(1, $byCurrency[1]['invoiced_month_count']);
        $this->assertSame('150.00', $byCurrency[1]['invoiced_month_sum']);
    }

    public function test_schedule_metrics_for_today_and_this_week(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $today = today();

        ScheduledJob::factory()->forObject($object)->planned()->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => $today->toDateString(),
        ]);
        ScheduledJob::factory()->forObject($object)->cancelled()->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => $today->toDateString(),
        ]);
        ScheduledJob::factory()->forObject($object)->planned()->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => $today->copy()->addDays(4)->toDateString(), // Sunday, this week
        ]);
        ScheduledJob::factory()->forObject($object)->planned()->create([
            'tenant_id' => $tenant->id,
            'scheduled_date' => $today->copy()->addDays(5)->toDateString(), // next Monday, next week
        ]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => $today->copy()->addDay()->toDateString(), // Thursday, this week
        ]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => $today->copy()->addDays(7)->toDateString(), // outside unassigned horizon
        ]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $schedule = $overview['companies'][0]['schedule'];
        $this->assertNotNull($schedule);
        $this->assertSame(1, $schedule['today']);
        $this->assertSame(3, $schedule['this_week']);
        $this->assertSame(1, $schedule['unassigned_next_7_days']);
        $this->assertFalse($schedule['own_only']);
    }

    public function test_cleaner_own_only_actor_sees_only_own_jobs(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $membership = TenantMembership::query()->where('user_id', $actor->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $colleague = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $today = today()->toDateString();

        ScheduledJob::factory()->forObject($object)->assignedTo($membership)->create(['tenant_id' => $tenant->id, 'scheduled_date' => $today]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => $today,
        ]);
        ScheduledJob::factory()->forObject($object)->create([
            'tenant_id' => $tenant->id,
            'status' => JobStatusEnum::Unassigned,
            'scheduled_date' => today()->addDay()->toDateString(),
        ]);
        ScheduledJob::factory()->forObject($object)->assignedTo($colleague)->create(['tenant_id' => $tenant->id, 'scheduled_date' => $today]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $company = $overview['companies'][0];
        $schedule = $company['schedule'];
        $this->assertNotNull($schedule);
        $this->assertSame(1, $schedule['today']);
        $this->assertSame(1, $schedule['this_week']);
        $this->assertNull($schedule['unassigned_next_7_days']);
        $this->assertTrue($schedule['own_only']);

        $alertTypes = collect($overview['alerts'])->pluck('type');
        $this->assertNotContains(DashboardAlertTypeEnum::UnassignedJob->value, $alertTypes);
        $this->assertNull($company['people']);
        $this->assertNull($company['invoices']);
        $this->assertNull($company['contracts']);
    }

    public function test_contracts_and_quotes_metrics_with_q3_category_override(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);
        $today = today();

        Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => $today->copy()->addDays(10)->toDateString()]);
        Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => $today->copy()->addDays(31)->toDateString()]);
        Contract::factory()->active()->indefinite()->create(['tenant_id' => $tenant->id]);
        Contract::factory()->draft()->create(['tenant_id' => $tenant->id]);

        // Q3 override: an active, fixed, soon-expiring EMPLOYMENT contract must never count
        // toward `active` / `expiring_30d` or surface a `contract_expiring` alert.
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        Contract::factory()->forMembership($membership)->active()->create([
            'tenant_id' => $tenant->id,
            'end_date' => $today->copy()->addDays(5)->toDateString(),
        ]);

        Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'valid_until' => $today->copy()->addDays(3)->toDateString()]);
        Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'valid_until' => $today->copy()->addDays(8)->toDateString()]);
        Quote::factory()->accepted()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $contracts = $overview['companies'][0]['contracts'];
        $this->assertNotNull($contracts);
        $this->assertSame(3, $contracts['active']);
        $this->assertSame(1, $contracts['expiring_30d']);
        $this->assertSame(2, $contracts['quotes_awaiting']);

        $alerts = collect($overview['alerts']);
        $contractAlerts = $alerts->where('type', DashboardAlertTypeEnum::ContractExpiring->value)->values();
        $this->assertCount(1, $contractAlerts);
        $firstContractAlert = $contractAlerts->first();
        $this->assertNotNull($firstContractAlert);
        $this->assertSame(10, $firstContractAlert['days']);

        $quoteAlerts = $alerts->where('type', DashboardAlertTypeEnum::QuoteExpiring->value)->values();
        $this->assertCount(1, $quoteAlerts);
        $firstQuoteAlert = $quoteAlerts->first();
        $this->assertNotNull($firstQuoteAlert);
        $this->assertSame(3, $firstQuoteAlert['days']);
    }

    public function test_people_metrics_counts_active_non_deleted_and_active_records(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);

        // +1 active membership is the acting admin itself -> 3 active total.
        TenantMembership::factory()->count(2)->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        Client::factory()->create(['tenant_id' => $tenant->id]);
        $deletedClient = Client::factory()->create(['tenant_id' => $tenant->id]);
        $deletedClient->delete();

        CleaningObject::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        $deletedObject = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        $deletedObject->delete();

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);

        $people = $overview['companies'][0]['people'];
        $this->assertNotNull($people);
        $this->assertSame(3, $people['employees']);
        $this->assertSame(2, $people['clients']);
        $this->assertSame(2, $people['objects']);
    }

    public function test_supplier_incomplete_alert_gated_by_permission(): void
    {
        // Admin -> settings.invoicing.
        $tenantAdmin = Tenant::factory()->create(['address_line' => '']);
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenantAdmin);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);
        $this->assertFalse($overview['companies'][0]['supplier_complete']);
        $alert = collect($overview['alerts'])->firstWhere('type', DashboardAlertTypeEnum::SupplierIncomplete->value);
        $this->assertNotNull($alert);
        $this->assertSame(route('settings.invoicing', absolute: false), $alert['url']);
        $this->assertSame(__('app.dashboard_alert_supplier_incomplete_title'), $alert['title']);
        $this->assertSame(__('app.dashboard_alert_supplier_incomplete_subtitle', ['fields' => __('app.street')]), $alert['subtitle']);

        // CreateInvoices only (no ManageBillingSettings) -> invoices.index.
        $tenantInvoices = Tenant::factory()->create(['address_line' => '']);
        $this->actingAsCustomPermissions($tenantInvoices, [PermissionEnum::CreateInvoices->value]);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);
        $alert = collect($overview['alerts'])->firstWhere('type', DashboardAlertTypeEnum::SupplierIncomplete->value);
        $this->assertNotNull($alert);
        $this->assertSame(route('invoices.index', absolute: false), $alert['url']);

        // Vedúca (neither permission) -> no alert.
        $tenantVedúca = Tenant::factory()->create(['address_line' => '']);
        $this->actingAsTenantUser('Vedúca', $tenantVedúca);

        $response = $this->get(route('dashboard'));
        $overview = $this->getOverview($response);
        $this->assertFalse($overview['companies'][0]['supplier_complete']);
        $alert = collect($overview['alerts'])->firstWhere('type', DashboardAlertTypeEnum::SupplierIncomplete->value);
        $this->assertNull($alert);
    }

    public function test_alert_ordering_and_caps_apply_per_type_and_total(): void
    {
        config(['dashboard.alerts.per_type_limit' => 2, 'dashboard.alerts.total_limit' => 3]);

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE, $tenant);
        $today = today();

        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'due_date' => $today->copy()->subDays(10)->toDateString()]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'due_date' => $today->copy()->subDays(3)->toDateString()]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'due_date' => $today->copy()->subDays(1)->toDateString()]);

        Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => $today->copy()->addDays(5)->toDateString()]);
        Contract::factory()->active()->create(['tenant_id' => $tenant->id, 'end_date' => $today->copy()->addDays(15)->toDateString()]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $overview = $this->getOverview($response);

        $alerts = $overview['alerts'];
        $this->assertCount(3, $alerts);
        $this->assertSame(DashboardAlertTypeEnum::OverdueInvoice->value, $alerts[0]['type']);
        $this->assertSame(10, $alerts[0]['days']);
        $this->assertSame(DashboardAlertTypeEnum::OverdueInvoice->value, $alerts[1]['type']);
        $this->assertSame(3, $alerts[1]['days']);
        $this->assertSame(DashboardAlertTypeEnum::ContractExpiring->value, $alerts[2]['type']);

        $overdueCount = collect($overview['alert_counts'])->firstWhere('type', DashboardAlertTypeEnum::OverdueInvoice->value);
        $this->assertNotNull($overdueCount);
        $this->assertSame(3, $overdueCount['count']);

        $contractCount = collect($overview['alert_counts'])->firstWhere('type', DashboardAlertTypeEnum::ContractExpiring->value);
        $this->assertNotNull($contractCount);
        $this->assertSame(2, $contractCount['count']);

        // Every alert url must be a relative path (single leading slash) — TenantSwitchData::redirect_to
        // rejects absolute / protocol-relative targets, so a cross-company alert click would 422 otherwise.
        foreach ($alerts as $alertRow) {
            $this->assertMatchesRegularExpression('#^/(?!/)#', $alertRow['url']);
        }
    }

    /** Adds `$user` as an (in)active member of `$tenant` under a seeded role template. */
    private function addMembership(User $user, Tenant $tenant, string $roleName, bool $active = true): void
    {
        TenantInterface::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        TenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => $active,
            'joined_at' => now(),
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);
        RoleTemplatesSeeder::seedForTenant($tenant);

        /** @var Role $role */
        $role = Role::inTenant($tenant->id)->where('name', $roleName)->firstOrFail();
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole($role);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($previous);
    }

    /**
     * Adds `$user` as an active member of `$tenant` with a bespoke permission set — used
     * where no seeded role template matches the exact combination a test needs.
     *
     * @param  list<string>  $permissions
     */
    private function addMembershipWithPermissions(User $user, Tenant $tenant, array $permissions): void
    {
        TenantInterface::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        TenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);
        $this->seed(PermissionSeeder::class);

        $role = Role::findOrCreate('Dashboard Test Role', 'web');
        $role->syncPermissions($permissions);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole($role);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $registrar->setPermissionsTeamId($previous);
    }

    /**
     * Logs in a brand-new user as the sole (active) member of `$tenant` with a bespoke
     * permission set, mirroring `TestCase::actingAsTenantUser` for cases no seeded role
     * template matches.
     *
     * @param  list<string>  $permissions
     */
    private function actingAsCustomPermissions(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create();
        TenantInterface::query()->firstOrCreate(['tenant_id' => $tenant->id]);
        TenantMembership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $role = Role::findOrCreate('Dashboard Test Role', 'web');
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        $this->actingAs($user);
        $this->bindTenant($tenant);

        return $user;
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return OverviewShape
     */
    private function getOverview(TestResponse $response): array
    {
        /** @var OverviewShape $overview */
        $overview = $response->inertiaProps('overview');

        return $overview;
    }
}
