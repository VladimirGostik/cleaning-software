<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceListFilterTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAdmin(): Tenant
    {
        $owner = User::factory()->create(['is_active' => true, 'locale' => 'sk']);
        $tenant = Tenant::factory()->forOwner($owner)->create();
        $this->actingAsTenantUser('Admin', $tenant);

        return $tenant;
    }

    public function test_filter_by_status(): void
    {
        $tenant = $this->tenantAdmin();
        Invoice::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->issued()->count(3)->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('invoices.index', ['filter[status]' => 'issued']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 3));
    }

    public function test_filter_by_client_id(): void
    {
        $tenant = $this->tenantAdmin();
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->count(2)->create(['tenant_id' => $tenant->id, 'client_id' => $clientA->id]);
        Invoice::factory()->count(4)->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

        $response = $this->get(route('invoices.index', ['filter[client_id]' => $clientA->id]));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 2));
    }

    public function test_filter_by_search_matches_number_and_customer_name(): void
    {
        $tenant = $this->tenantAdmin();
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_name' => 'Alpha Corp']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_name' => 'Beta Ltd']);

        $response = $this->get(route('invoices.index', ['filter[search]' => 'Alpha']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 1));
    }

    public function test_filter_by_issue_date_between(): void
    {
        $tenant = $this->tenantAdmin();
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-05']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-02-15']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-06-01']);

        $response = $this->get(route('invoices.index', ['filter[issue_date]' => 'between:2026-01-01,2026-03-01']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 2));
    }

    public function test_filter_by_total_greater_than_or_equal(): void
    {
        $tenant = $this->tenantAdmin();
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '50.00']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '150.00']);

        $response = $this->get(route('invoices.index', ['filter[total]' => '>=:100']));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 1));
    }

    public function test_cross_tenant_invoices_never_returned(): void
    {
        $tenant = $this->tenantAdmin();
        $other = Tenant::factory()->create();
        Invoice::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        Invoice::factory()->count(5)->create(['tenant_id' => $other->id]);

        $response = $this->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Invoices/Index')->has('invoices.data', 2));
    }
}
