<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class InvoiceBulkIdsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    /**
     * @param  array<string, string>  $query
     * @return list<string>
     */
    private function indexIds(array $query): array
    {
        $ids = [];

        $this->get(route('invoices.index', $query + ['per_page' => 100]))->assertInertia(
            function (AssertableInertia $page) use (&$ids): void {
                /** @var array{props: array{invoices: array{data: list<array{id: string}>}}} $payload */
                $payload = $page->toArray();
                $ids = array_column($payload['props']['invoices']['data'], 'id');
            },
        );

        return $ids;
    }

    public function test_ids_follow_the_index_order_for_status_filter_and_sort(): void
    {
        foreach ([3, 1, 2] as $day) {
            Invoice::factory()->issued()->create([
                'tenant_id' => $this->tenant->id,
                'issue_date' => now()->subDays($day),
                'created_at' => now()->subHours($day),
            ]);
        }
        Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach ([['filter[status]' => 'issued'], ['filter[status]' => 'issued', 'sort' => 'issue_date'], ['sort' => '-issue_date']] as $query) {
            $response = $this->getJson(route('invoices.bulk.ids', $query))->assertOk();

            $this->assertSame($this->indexIds($query), $response->json('ids'));
        }
    }

    public function test_issue_date_range_filter_is_applied(): void
    {
        $in = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id, 'issue_date' => '2026-10-15']);
        Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id, 'issue_date' => '2026-09-15']);
        Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id, 'issue_date' => '2026-11-01']);
        $query = ['filter[issue_date]' => 'between:2026-10-01,2026-10-31'];

        $response = $this->getJson(route('invoices.bulk.ids', $query))->assertOk();

        $this->assertSame([$in->id], $response->json('ids'));
        $this->assertSame($this->indexIds($query), $response->json('ids'));
    }

    public function test_total_is_uncapped_and_ids_are_truncated_above_the_cap(): void
    {
        config()->set('invoicing.bulk.print_all_max', 3);
        Invoice::factory()->issued()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson(route('invoices.bulk.ids'))->assertOk();

        $this->assertCount(3, (array) $response->json('ids'));
        $this->assertSame(5, $response->json('total'));
        $this->assertTrue($response->json('truncated'));
        $this->assertSame(array_slice($this->indexIds([]), 0, 3), $response->json('ids'));
    }

    public function test_page_and_per_page_params_do_not_limit_the_ids(): void
    {
        Invoice::factory()->issued()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson(route('invoices.bulk.ids', ['page' => 2, 'per_page' => 1]))->assertOk();

        $this->assertCount(4, (array) $response->json('ids'));
        $this->assertSame(4, $response->json('total'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        auth()->logout();

        $this->getJson(route('invoices.bulk.ids'))->assertUnauthorized();
    }

    public function test_not_truncated_at_or_below_the_cap(): void
    {
        config()->set('invoicing.bulk.print_all_max', 2);
        Invoice::factory()->issued()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->getJson(route('invoices.bulk.ids'))->assertOk()->assertJson(['total' => 2, 'truncated' => false]);
    }

    public function test_empty_result_and_foreign_tenant_invoices_are_excluded(): void
    {
        Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->getJson(route('invoices.bulk.ids'))->assertOk()->assertExactJson(['ids' => [], 'total' => 0, 'truncated' => false]);
    }

    public function test_forbidden_without_view_invoices(): void
    {
        $this->actingAsTenantUser('Interná upratovačka', $this->tenant);

        $this->getJson(route('invoices.bulk.ids'))->assertForbidden();
    }
}
