<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceItemUnitMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_11_14_000100_fill_missing_item_units.php');
        $this->assertTrue($migration instanceof Migration && method_exists($migration, 'up'));
        $migration->up();
    }

    private function item(Invoice $invoice, ?string $unit): InvoiceItem
    {
        return InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id, 'unit' => $unit]);
    }

    public function test_fills_missing_units_on_recurring_and_invoice_items_of_every_status(): void
    {
        $tenant = Tenant::factory()->create();
        $recurring = RecurringInvoice::factory()->create(['tenant_id' => $tenant->id]);
        $recurringItem = RecurringInvoiceItem::factory()->create(['recurring_invoice_id' => $recurring->id, 'tenant_id' => $tenant->id, 'unit' => null]);
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id]);
        $draftNull = $this->item($draft, null);
        $draftHod = $this->item($draft, 'hod.');
        $issuedNull = $this->item($issued, null);

        $this->runMigration();

        $this->assertSame('mes.', $recurringItem->refresh()->unit);
        $this->assertSame('mes.', $draftNull->refresh()->unit);
        $this->assertSame('hod.', $draftHod->refresh()->unit);
        $this->assertSame('mes.', $issuedNull->refresh()->unit);
    }

    public function test_second_run_changes_nothing(): void
    {
        $tenant = Tenant::factory()->create();
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id]);
        $item = $this->item($draft, null);

        $this->runMigration();
        $item->refresh()->update(['unit' => 'ks']);
        $this->runMigration();

        $this->assertSame('ks', $item->refresh()->unit);
    }
}
