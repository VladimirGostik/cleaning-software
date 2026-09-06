<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_aggregate_tenant_scoped_sums_and_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '100.00', 'issue_date' => now()->toDateString()]);
        Invoice::factory()->overdue()->create(['tenant_id' => $tenant->id, 'total' => '200.00', 'issue_date' => now()->toDateString()]);
        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'total' => '50.00', 'type' => InvoiceTypeEnum::Monthly, 'issue_date' => now()->toDateString()]);

        $other = Tenant::factory()->create();
        Invoice::factory()->issued()->create(['tenant_id' => $other->id, 'total' => '999.00']);

        $stats = app(InvoiceService::class)->stats();

        $this->assertSame(3, $stats->issued_this_month->count);
        $this->assertSame(1, $stats->overdue->count);
        $this->assertSame(2, $stats->pending->count);
        $this->assertSame(1, $stats->recurring_monthly->count);
        $this->assertSame(CurrencyEnum::EUR, $stats->currency);
    }

    public function test_stats_returns_zeros_for_empty_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $stats = app(InvoiceService::class)->stats();

        $this->assertSame(0, $stats->issued_this_month->count);
        $this->assertSame('0.00', $stats->issued_this_month->amount);
        $this->assertSame(0, $stats->overdue->count);
        $this->assertSame(0, $stats->pending->count);
        $this->assertSame(0, $stats->recurring_monthly->count);
    }
}
