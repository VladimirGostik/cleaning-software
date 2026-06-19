<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
use App\Data\RecurringInvoices\RecurringInvoiceIndexFilterData;
use App\Data\RecurringInvoices\RecurringInvoiceListItemData;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the customer_display_name fix.
 *
 * Bug: client-linked RecurringInvoice showed "no customer" because customer_name is NULL
 * by design for client-linked templates.
 * Fix: both DTOs now expose customer_display_name resolved from the relation chain:
 *   client->name ?? cleaningObject->client->name ?? customer_name
 */
final class RecurringInvoiceCustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // RecurringInvoiceDetailData::fromModel
    // -------------------------------------------------------------------------

    public function test_detail_data_client_linked_resolves_client_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $client = Client::factory()->create([
            'tenant_id' => $tenantId,
            'name' => 'ACME s.r.o.',
        ]);

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'customer_name' => null,
        ]);

        // Act
        $data = RecurringInvoiceDetailData::fromModel($ri);

        // Assert
        $this->assertSame('ACME s.r.o.', $data->customer_display_name);
        // customer_name stays null by design
        $this->assertNull($data->customer_name);
    }

    public function test_detail_data_object_linked_resolves_object_client_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $client = Client::factory()->create([
            'tenant_id' => $tenantId,
            'name' => 'Beta Ltd',
        ]);

        $obj = CleaningObject::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
        ]);

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => $obj->id,
            'customer_name' => null,
        ]);

        // Act
        $data = RecurringInvoiceDetailData::fromModel($ri);

        // Assert
        $this->assertSame('Beta Ltd', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_detail_data_standalone_with_name_uses_customer_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => null,
            'customer_name' => 'Manual Name',
        ]);

        // Act
        $data = RecurringInvoiceDetailData::fromModel($ri);

        // Assert
        $this->assertSame('Manual Name', $data->customer_display_name);
        $this->assertSame('Manual Name', $data->customer_name);
    }

    public function test_detail_data_standalone_no_name_resolves_null(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => null,
            'customer_name' => null,
        ]);

        // Act
        $data = RecurringInvoiceDetailData::fromModel($ri);

        // Assert
        $this->assertNull($data->customer_display_name);
    }

    // -------------------------------------------------------------------------
    // RecurringInvoiceListItemData::fromModel
    // (preventLazyLoading is on in non-production; relations must be pre-loaded)
    // -------------------------------------------------------------------------

    public function test_list_item_data_client_linked_resolves_client_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $client = Client::factory()->create([
            'tenant_id' => $tenantId,
            'name' => 'ACME s.r.o.',
        ]);

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
            'cleaning_object_id' => null,
            'customer_name' => null,
        ]);

        // Act
        $ri->load(['client', 'cleaningObject.client']);
        $data = RecurringInvoiceListItemData::fromModel($ri);

        // Assert
        $this->assertSame('ACME s.r.o.', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_list_item_data_object_linked_resolves_object_client_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $client = Client::factory()->create([
            'tenant_id' => $tenantId,
            'name' => 'Beta Ltd',
        ]);

        $obj = CleaningObject::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => $client->id,
        ]);

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => $obj->id,
            'customer_name' => null,
        ]);

        // Act
        $ri->load(['client', 'cleaningObject.client']);
        $data = RecurringInvoiceListItemData::fromModel($ri);

        // Assert
        $this->assertSame('Beta Ltd', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_list_item_data_standalone_with_name_uses_customer_name(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => null,
            'customer_name' => 'Manual Name',
        ]);

        // Act
        $ri->load(['client', 'cleaningObject.client']);
        $data = RecurringInvoiceListItemData::fromModel($ri);

        // Assert
        $this->assertSame('Manual Name', $data->customer_display_name);
        $this->assertSame('Manual Name', $data->customer_name);
    }

    public function test_list_item_data_standalone_no_name_resolves_null(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => null,
            'customer_name' => null,
        ]);

        // Act
        $ri->load(['client', 'cleaningObject.client']);
        $data = RecurringInvoiceListItemData::fromModel($ri);

        // Assert
        $this->assertNull($data->customer_display_name);
    }

    // -------------------------------------------------------------------------
    // N+1 guard: paginate with 18 mixed rows stays in bounded query count
    // -------------------------------------------------------------------------

    public function test_paginate_does_not_produce_n_plus_1_queries_for_mixed_rows(): void
    {
        // Arrange
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        // 6 client-linked rows
        Client::factory()->count(6)->create(['tenant_id' => $tenantId])
            ->each(function (Client $client) use ($tenantId): void {
                RecurringInvoice::factory()->create([
                    'tenant_id' => $tenantId,
                    'client_id' => $client->id,
                    'cleaning_object_id' => null,
                    'customer_name' => null,
                ]);
            });

        // 6 object-linked rows (each object belongs to a distinct client)
        for ($i = 0; $i < 6; $i++) {
            $client = Client::factory()->create(['tenant_id' => $tenantId]);
            $obj = CleaningObject::factory()->create([
                'tenant_id' => $tenantId,
                'client_id' => $client->id,
            ]);
            RecurringInvoice::factory()->create([
                'tenant_id' => $tenantId,
                'client_id' => null,
                'cleaning_object_id' => $obj->id,
                'customer_name' => null,
            ]);
        }

        // 6 standalone rows
        RecurringInvoice::factory()->count(6)->create([
            'tenant_id' => $tenantId,
            'client_id' => null,
            'cleaning_object_id' => null,
        ]);

        $filter = RecurringInvoiceIndexFilterData::from(['per_page' => 100]);
        $service = app(RecurringInvoiceService::class);

        // Act
        DB::flushQueryLog();
        DB::enableQueryLog();
        $paginator = $service->paginate($filter);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Assert
        // Rows fetched: 18. N+1 would produce 2 + 18*2 = 38 queries.
        // Eager loading (with ['client', 'cleaningObject.client']) produces:
        //   1 COUNT + 1 SELECT + 1 clients + 1 objects + 1 object-clients = 5
        // Allow ≤ 6 to absorb minor implementation variation.
        $this->assertSame(18, $paginator->total());
        $this->assertLessThanOrEqual(
            6,
            $queryCount,
            "Expected ≤ 6 queries with eager loading for 18 rows, got {$queryCount}. N+1 suspected.",
        );
    }
}
