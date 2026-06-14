<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceListExpansionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Advanced filters — issued_from / issued_to
    // -------------------------------------------------------------------------

    public function test_issued_from_filter_narrows_results(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-10', 'customer_name' => 'Old']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-03-15', 'customer_name' => 'New']);

        $response = $this->get(route('invoices.index', ['issued_from' => '2026-02-01']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'New'),
        );
    }

    public function test_issued_to_filter_narrows_results(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-10', 'customer_name' => 'Old']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-03-15', 'customer_name' => 'New']);

        $response = $this->get(route('invoices.index', ['issued_to' => '2026-02-01']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Old'),
        );
    }

    public function test_total_min_filter_excludes_lower_totals(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '50.00', 'customer_name' => 'Cheap']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '200.00', 'customer_name' => 'Expensive']);

        $response = $this->get(route('invoices.index', ['total_min' => '100']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Expensive'),
        );
    }

    public function test_total_max_filter_excludes_higher_totals(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '50.00', 'customer_name' => 'Cheap']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '200.00', 'customer_name' => 'Expensive']);

        $response = $this->get(route('invoices.index', ['total_max' => '100']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Cheap'),
        );
    }

    public function test_filters_combined_with_month_both_apply(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-03-05', 'customer_name' => 'Early']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-03-25', 'customer_name' => 'Late']);

        $response = $this->get(route('invoices.index', [
            'month' => '2026-03',
            'issued_from' => '2026-03-20',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Late'),
        );
    }

    public function test_only_issued_from_returns_open_ended_upper(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->count(3)->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-05-01']);
        Invoice::factory()->count(2)->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-01']);

        $response = $this->get(route('invoices.index', ['issued_from' => '2026-04-01']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 3),
        );
    }

    public function test_total_min_equals_total_max_returns_exact_match(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '100.00', 'customer_name' => 'Exact']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '99.00', 'customer_name' => 'Under']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'total' => '101.00', 'customer_name' => 'Over']);

        $response = $this->get(route('invoices.index', ['total_min' => '100', 'total_max' => '100']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'Exact'),
        );
    }

    public function test_advanced_filter_returns_empty_result_set(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-01']);

        $response = $this->get(route('invoices.index', ['issued_from' => '2027-01-01']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 0),
        );
    }

    // -------------------------------------------------------------------------
    // Filter validation failures (web route → redirect with session errors)
    // -------------------------------------------------------------------------

    public function test_malformed_issued_from_date_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.index', ['issued_from' => '2026-13-99']));

        $response->assertRedirect();
        $response->assertSessionHasErrors('issued_from');
    }

    public function test_negative_total_min_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.index', ['total_min' => '-10']));

        $response->assertRedirect();
        $response->assertSessionHasErrors('total_min');
    }

    // -------------------------------------------------------------------------
    // Index returns clients prop (FE precondition)
    // -------------------------------------------------------------------------

    public function test_index_returns_clients_prop(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('clients'),
        );
    }

    // -------------------------------------------------------------------------
    // Tab filter — all_issued excludes Draft and Cancelled
    // -------------------------------------------------------------------------

    public function test_tab_all_issued_excludes_draft_and_cancelled_invoices(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'customer_name' => 'DraftInvoice']);
        Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_name' => 'IssuedInvoice']);
        Invoice::factory()->cancelled()->create(['tenant_id' => $tenant->id, 'customer_name' => 'CancelledInvoice']);

        $response = $this->get(route('invoices.index', ['tab' => 'all_issued']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.customer_name', 'IssuedInvoice'),
        );
    }

    // -------------------------------------------------------------------------
    // CSV export — GET /invoices/export
    // -------------------------------------------------------------------------

    public function test_export_returns_csv_with_bom_and_header_row(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_name' => 'Export Corp',
            'status' => InvoiceStatusEnum::Draft,
            'type' => InvoiceTypeEnum::OneOff,
        ]);

        $response = $this->get(route('invoices.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('number,customer_name,object_name,type,issue_date,due_date,total,status', $content);
        $this->assertStringContainsString('Export Corp', $content);
    }

    public function test_export_zero_matching_invoices_returns_header_row_only(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.export', ['issued_from' => '2099-01-01']));

        $response->assertOk();

        $content = $response->streamedContent();
        $withoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = array_filter(explode("\n", trim($withoutBom)));

        $this->assertCount(1, $lines);
    }

    public function test_export_honors_same_filters_as_index(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-01-01', 'customer_name' => 'Old']);
        Invoice::factory()->create(['tenant_id' => $tenant->id, 'issue_date' => '2026-06-01', 'customer_name' => 'Recent']);

        $response = $this->get(route('invoices.export', ['issued_from' => '2026-05-01']));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Recent', $content);
        $this->assertStringNotContainsString('Old', $content);
    }

    public function test_user_without_view_invoices_cannot_export(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('invoices.export'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_export(): void
    {
        $response = $this->get(route('invoices.export'));

        $response->assertRedirect();
    }

    public function test_export_cross_tenant_invoices_excluded(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_name' => 'OtherTenantCorp',
        ]);

        $response = $this->get(route('invoices.export'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('OtherTenantCorp', $content);
    }

    // -------------------------------------------------------------------------
    // Bulk mark_paid — POST /invoices/bulk
    // -------------------------------------------------------------------------

    public function test_bulk_mark_paid_all_issued_invoices(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $invoices = Invoice::factory()->count(3)->sequence(
            ['number' => 'INV-001'],
            ['number' => 'INV-002'],
            ['number' => 'INV-003'],
        )->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued,
        ]);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => $invoices->pluck('id')->all(),
        ]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 3, 'failed' => 0, 'errors' => []]);

        $invoices->each(fn (Invoice $i) => $this->assertDatabaseHas('invoices', [
            'id' => $i->id,
            'status' => InvoiceStatusEnum::Paid->value,
        ]));
    }

    public function test_bulk_action_cancel_rejected_with_422(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'cancel',
            'ids' => ['fake-id'],
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_without_edit_invoices_permission_returns_403(): void
    {
        $user = $this->actingAsTenantUser('Upratovačka');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        $response->assertForbidden();
    }

    public function test_bulk_cross_tenant_id_excluded_as_failed(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $otherTenant = Tenant::factory()->create();
        $otherInvoice = Invoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => InvoiceStatusEnum::Issued,
            'number' => 'INV-OTHER-001',
        ]);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => [$otherInvoice->id],
        ]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 0, 'failed' => 1]);

        $this->assertDatabaseHas('invoices', [
            'id' => $otherInvoice->id,
            'status' => InvoiceStatusEnum::Issued->value,
        ]);
    }

    public function test_bulk_partial_success_mix_payable_and_non_payable(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $payable = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Issued,
            'number' => 'INV-PAY-001',
        ]);
        $draft = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => InvoiceStatusEnum::Draft,
            'number' => null,
        ]);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => [$payable->id, $draft->id],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(1, $data['succeeded']);
        $this->assertSame(1, $data['failed']);
        $this->assertNotEmpty($data['errors']);
    }

    public function test_bulk_empty_ids_returns_422(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_bulk_more_than_200_ids_returns_422(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ids = array_map(fn () => fake()->uuid(), range(1, 201));

        $response = $this->postJson(route('invoices.bulk'), [
            'action' => 'mark_paid',
            'ids' => $ids,
        ]);

        $response->assertStatus(422);
    }
}
