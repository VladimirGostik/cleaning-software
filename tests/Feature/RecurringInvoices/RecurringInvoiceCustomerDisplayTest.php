<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Data\RecurringInvoices\RecurringInvoiceDetailData;
use App\Data\RecurringInvoices\RecurringInvoiceListItemData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * customer_display_name resolves client->name ?? cleaningObject->client->name ?? customer_name
 * across client-linked, object-linked and standalone RecurringInvoice rows.
 */
final class RecurringInvoiceCustomerDisplayTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // RecurringInvoiceDetailData::fromModel
    // -------------------------------------------------------------------------

    public function test_detail_data_client_linked_resolves_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ACME s.r.o.']);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cleaning_object_id' => null, 'customer_name' => null]);

        $data = RecurringInvoiceDetailData::fromModel($ri);

        $this->assertSame('ACME s.r.o.', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_detail_data_object_linked_resolves_object_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta Ltd']);
        $obj = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => $obj->id, 'customer_name' => null]);

        $data = RecurringInvoiceDetailData::fromModel($ri);

        $this->assertSame('Beta Ltd', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_detail_data_standalone_with_name_uses_customer_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => null, 'customer_name' => 'Manual Name']);

        $data = RecurringInvoiceDetailData::fromModel($ri);

        $this->assertSame('Manual Name', $data->customer_display_name);
        $this->assertSame('Manual Name', $data->customer_name);
    }

    public function test_detail_data_standalone_no_name_resolves_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => null, 'customer_name' => null]);

        $data = RecurringInvoiceDetailData::fromModel($ri);

        $this->assertNull($data->customer_display_name);
    }

    // -------------------------------------------------------------------------
    // RecurringInvoiceListItemData::fromModel
    // -------------------------------------------------------------------------

    public function test_list_item_data_client_linked_resolves_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ACME s.r.o.']);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'cleaning_object_id' => null, 'customer_name' => null]);
        $ri->load(['client', 'cleaningObject.client']);

        $data = RecurringInvoiceListItemData::fromModel($ri);

        $this->assertSame('ACME s.r.o.', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_list_item_data_object_linked_resolves_object_client_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta Ltd']);
        $obj = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => $obj->id, 'customer_name' => null]);
        $ri->load(['client', 'cleaningObject.client']);

        $data = RecurringInvoiceListItemData::fromModel($ri);

        $this->assertSame('Beta Ltd', $data->customer_display_name);
        $this->assertNull($data->customer_name);
    }

    public function test_list_item_data_standalone_with_name_uses_customer_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => null, 'customer_name' => 'Manual Name']);
        $ri->load(['client', 'cleaningObject.client']);

        $data = RecurringInvoiceListItemData::fromModel($ri);

        $this->assertSame('Manual Name', $data->customer_display_name);
        $this->assertSame('Manual Name', $data->customer_name);
    }

    public function test_list_item_data_standalone_no_name_resolves_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id, 'client_id' => null, 'cleaning_object_id' => null, 'customer_name' => null]);
        $ri->load(['client', 'cleaningObject.client']);

        $data = RecurringInvoiceListItemData::fromModel($ri);

        $this->assertNull($data->customer_display_name);
    }

    // -------------------------------------------------------------------------
    // N+1 guard
    // -------------------------------------------------------------------------

    public function test_paginate_does_not_produce_n_plus_1_queries_for_mixed_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        Client::factory()->count(6)->create(['tenant_id' => $tenant->id])
            ->each(function (Client $client) use ($tenant): void {
                RecurringInvoice::factory()->create([
                    'tenant_id' => $tenant->id,
                    'client_id' => $client->id,
                    'cleaning_object_id' => null,
                    'customer_name' => null,
                ]);
            });

        for ($i = 0; $i < 6; $i++) {
            $client = Client::factory()->create(['tenant_id' => $tenant->id]);
            $obj = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
            RecurringInvoice::factory()->create([
                'tenant_id' => $tenant->id,
                'client_id' => null,
                'cleaning_object_id' => $obj->id,
                'customer_name' => null,
            ]);
        }

        RecurringInvoice::factory()->count(6)->create([
            'tenant_id' => $tenant->id,
            'client_id' => null,
            'cleaning_object_id' => null,
        ]);

        $service = app(RecurringInvoiceService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $paginator = $service->paginate(Request::create('/recurring-invoices', 'GET', ['per_page' => '100']));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(18, $paginator->total());
        $this->assertLessThanOrEqual(
            6,
            $queryCount,
            "Expected <= 6 queries with eager loading for 18 rows, got {$queryCount}. N+1 suspected.",
        );
    }
}
