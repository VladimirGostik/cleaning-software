<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvoiceStatsTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. InvoiceService::stats() — tenant isolation
    // -------------------------------------------------------------------------

    public function test_stats_are_scoped_to_active_tenant_and_exclude_other_tenants(): void
    {
        // Arrange — tenant A
        $userA = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($userA, SubscriptionPlanEnum::Pro);
        $tenantA = Tenant::where('owner_id', $userA->id)->first();

        // 2 Issued this month for tenant A
        Invoice::factory()->issued()->count(2)->create([
            'tenant_id' => $tenantA->id,
            'issue_date' => now()->startOfMonth()->toDateString(),
            'total' => '150.00',
        ]);

        // 1 Overdue for tenant A — pin to previous month so it does NOT count in issued_this_month
        Invoice::factory()->overdue()->create([
            'tenant_id' => $tenantA->id,
            'total' => '200.00',
            'issue_date' => now()->subMonthNoOverflow()->toDateString(),
        ]);

        // 1 Monthly + Issued for tenant A (counts in issued_this_month too if this month)
        Invoice::factory()->issued()->create([
            'tenant_id' => $tenantA->id,
            'type' => InvoiceTypeEnum::Monthly,
            'issue_date' => now()->startOfMonth()->toDateString(),
            'total' => '300.00',
        ]);

        // Arrange — tenant B (5 Issued invoices — must NOT appear in tenant A stats)
        $tenantB = Tenant::factory()->create();
        Invoice::factory()->issued()->count(5)->create([
            'tenant_id' => $tenantB->id,
            'issue_date' => now()->startOfMonth()->toDateString(),
            'total' => '999.00',
        ]);

        // Act — restore tenant A context and call service
        app()->instance('current_tenant_id', $tenantA->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);

        $stats = app(InvoiceService::class)->stats();

        // Assert — tenant B invoices absent; tenant A counts correct
        // issued_this_month: 2 Issued + 1 Monthly/Issued = 3
        $this->assertSame(3, $stats->issued_this_month->count);
        // overdue: 1 Overdue invoice
        $this->assertSame(1, $stats->overdue->count);
        // tenant B's 5 invoices must not inflate any card
        $this->assertSame(0 + 3, $stats->issued_this_month->count); // redundant but explicit
        $this->assertNotSame(5, $stats->issued_this_month->count);
    }

    public function test_stats_issued_this_month_count_is_two_for_non_monthly_issued(): void
    {
        // Arrange — only 2 plain Issued invoices this month, no Overdue/Monthly
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->issued()->count(2)->create([
            'tenant_id' => $tenant->id,
            'issue_date' => now()->toDateString(),
        ]);

        // Draft does not count
        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'issue_date' => now()->toDateString(),
        ]);

        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $stats = app(InvoiceService::class)->stats();

        // Assert
        $this->assertSame(2, $stats->issued_this_month->count);
    }

    public function test_stats_overdue_count_is_one(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->issued()->count(2)->create(['tenant_id' => $tenant->id]);

        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $stats = app(InvoiceService::class)->stats();

        // Assert
        $this->assertSame(1, $stats->overdue->count);
    }

    // -------------------------------------------------------------------------
    // 2. InvoiceService::tabCounts() — tenant isolation + overdue overlap
    // -------------------------------------------------------------------------

    public function test_tab_counts_scoped_to_active_tenant_exclude_other_tenant(): void
    {
        // Arrange — tenant A: 2 Issued, 1 Overdue
        $userA = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($userA, SubscriptionPlanEnum::Pro);
        $tenantA = Tenant::where('owner_id', $userA->id)->first();

        Invoice::factory()->issued()->count(2)->create(['tenant_id' => $tenantA->id]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenantA->id]);

        // Tenant B: 5 Issued — must not appear in tenant A tab counts
        $tenantB = Tenant::factory()->create();
        Invoice::factory()->issued()->count(5)->create(['tenant_id' => $tenantB->id]);

        app()->instance('current_tenant_id', $tenantA->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);

        // Act
        $counts = app(InvoiceService::class)->tabCounts();

        // Assert — tenant A only: 2 Issued + 1 Overdue = 3 in all_issued; 1 in overdue
        $this->assertSame(3, $counts['all_issued']);
        $this->assertSame(1, $counts['overdue']);
        // Tenant B's 5 must not appear
        $this->assertNotSame(8, $counts['all_issued']);
    }

    public function test_overdue_invoice_appears_in_both_all_issued_and_overdue_tab_counts(): void
    {
        // Arrange — 1 Overdue + 1 plain Issued
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);

        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $counts = app(InvoiceService::class)->tabCounts();

        // Assert — by design: Overdue appears in all_issued (Issued|Overdue|Paid filter) AND overdue
        $this->assertSame(2, $counts['all_issued']); // Overdue + Issued
        $this->assertSame(1, $counts['overdue']);     // only Overdue
    }

    // -------------------------------------------------------------------------
    // 3. InvoiceService::stats() — zero-invoice tenant
    // -------------------------------------------------------------------------

    public function test_stats_returns_zero_amounts_and_counts_for_tenant_with_no_invoices(): void
    {
        // Arrange — fresh tenant, no invoices at all
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        app()->instance('current_tenant_id', $tenant->id);

        // Act
        $stats = app(InvoiceService::class)->stats();

        // Assert — all 4 cards zero
        $this->assertSame('0.00', $stats->issued_this_month->amount);
        $this->assertSame(0, $stats->issued_this_month->count);

        $this->assertSame('0.00', $stats->overdue->amount);
        $this->assertSame(0, $stats->overdue->count);

        $this->assertSame('0.00', $stats->pending->amount);
        $this->assertSame(0, $stats->pending->count);

        $this->assertSame('0.00', $stats->recurring_monthly->amount);
        $this->assertSame(0, $stats->recurring_monthly->count);
    }

    // -------------------------------------------------------------------------
    // 4. GET /invoices?tab= — regression for Max(7)→Max(10) fix
    // -------------------------------------------------------------------------

    public function test_index_with_tab_all_issued_returns_200(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act & Assert
        $this->get(route('invoices.index', ['tab' => 'all_issued']))->assertOk();
    }

    public function test_index_with_tab_recurring_returns_200(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act & Assert
        $this->get(route('invoices.index', ['tab' => 'recurring']))->assertOk();
    }

    // -------------------------------------------------------------------------
    // 5. GET /invoices?month= — filter echoed back in Inertia props
    // -------------------------------------------------------------------------

    public function test_index_with_month_filter_returns_200_and_echoes_month_prop(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act
        $response = $this->get(route('invoices.index', ['month' => '2026-06']));

        // Assert — HTTP 200
        $response->assertOk();

        // Assert — filters.month echoed back to Inertia
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('filters.month', '2026-06'),
        );
    }

    public function test_index_with_invalid_month_format_fails_validation(): void
    {
        // Arrange — month must match Y-m format (date_format:Y-m)
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Act — supply malformed month
        $response = $this->get(route('invoices.index', ['month' => '06-2026']));

        // Assert — Spatie Data DTO validation redirects back with errors
        $response->assertSessionHasErrors('month');
    }
}
