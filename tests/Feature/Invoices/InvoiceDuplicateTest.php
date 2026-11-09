<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AssertsFullRows;
use Tests\Support\BuildsFullInvoices;
use Tests\TestCase;

final class InvoiceDuplicateTest extends TestCase
{
    use AssertsFullRows;
    use BuildsFullInvoices;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0, 0));
        $this->tenant = Tenant::factory()->create();
        $this->bindTenant($this->tenant);
    }

    public function test_duplicate_of_filled_paid_invoice_copies_snapshot_and_resets_lifecycle_columns(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertFullRow($copy, [
            ...$this->carriedInvoiceColumns(),
            'tenant_id' => $source->tenant_id,
            'client_id' => $source->client_id,
            'cleaning_object_id' => $source->cleaning_object_id,
            'status' => 'draft',
            'number' => null,
            'variable_symbol' => null,
            'credited_invoice_id' => null,
            'recurring_invoice_id' => null,
            'quote_id' => null,
            'period_from' => null,
            'period_to' => null,
            'issue_date' => '2026-11-04',
            'delivery_date' => '2026-11-04',
            'due_date' => '2026-11-18',
            'issued_at' => null,
            'issued_by_name' => null,
            'sent_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'paid_amount' => '0.00',
            'supplier_signature_media_id' => null,
            'efa_status' => 'none',
            'efa_id' => null,
            'efa_submitted_at' => null,
            'efa_last_error' => null,
            'deleted_at' => null,
            'legacy_id' => null,
        ]);
    }

    public function test_duplicate_copies_every_item_column_and_returns_its_own_items(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertTrue($copy->relationLoaded('items'));
        $this->assertCount(3, $copy->items);
        $this->assertCount(3, $source->items);

        $copyItems = $copy->items->all();
        $sourceItems = $source->items->all();

        foreach ($this->sourceItemColumns() as $index => $columns) {
            $item = $copyItems[$index];
            $this->assertNotSame($sourceItems[$index]->id, $item->id);
            $this->assertFullRow($item, [
                ...$columns,
                'tenant_id' => $source->tenant_id,
                'invoice_id' => $copy->id,
            ]);
        }

        $this->assertSame(3, InvoiceItem::query()->where('invoice_id', $source->id)->count());
        $this->assertSame(3, InvoiceItem::query()->where('invoice_id', $copy->id)->count());
    }

    public function test_duplicate_leaves_the_source_untouched(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);
        $before = (array) DB::table('invoices')->where('id', $source->id)->first();

        app(InvoiceService::class)->duplicate($source);

        $this->assertEquals($before, (array) DB::table('invoices')->where('id', $source->id)->first());
    }

    public function test_duplicate_writes_a_created_activity_row_with_the_logged_attributes(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertSame([
            'status' => 'draft',
            'number' => null,
            'total' => '360.00',
            'client_id' => $source->client_id,
            'issued_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'sent_at' => null,
            'efa_status' => 'none',
            'customer_email' => 'buyer@example.test',
        ], $this->createdActivityAttributes($copy));
    }

    public function test_duplicate_dispatches_nothing(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);
        Queue::fake();
        Notification::fake();

        app(InvoiceService::class)->duplicate($source);

        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_duplicate_of_invoice_without_items_has_no_items(): void
    {
        $source = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id]);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertCount(0, $copy->items);
        $this->assertSame(0, InvoiceItem::query()->where('invoice_id', $copy->id)->count());
    }

    public function test_duplicate_due_date_follows_configured_default_due_days(): void
    {
        config(['invoicing.default_due_days' => 30]);
        $source = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id]);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertSame('2026-12-04', $copy->due_date->toDateString());
    }

    public function test_duplicate_falls_back_to_14_days_when_config_is_not_numeric(): void
    {
        config(['invoicing.default_due_days' => 'soon']);
        $source = Invoice::factory()->issued()->create(['tenant_id' => $this->tenant->id]);

        $copy = app(InvoiceService::class)->duplicate($source);

        $this->assertSame('2026-11-18', $copy->due_date->toDateString());
    }

    public function test_duplicating_twice_creates_two_independent_drafts(): void
    {
        $source = $this->fullInvoiceSource($this->tenant);

        $first = app(InvoiceService::class)->duplicate($source);
        $second = app(InvoiceService::class)->duplicate($source);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(6, InvoiceItem::query()->whereIn('invoice_id', [$first->id, $second->id])->count());
        $this->assertSame(3, InvoiceItem::query()->where('invoice_id', $source->id)->count());
    }
}
