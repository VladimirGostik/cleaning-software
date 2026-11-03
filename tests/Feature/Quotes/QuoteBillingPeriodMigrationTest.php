<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuoteBillingPeriodMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string PATH = 'database/migrations/2026_11_09_000100_add_billing_periods_to_quotes.php';

    private function rollbackMigration(): void
    {
        Artisan::call('migrate:rollback', ['--path' => self::PATH, '--force' => true]);
    }

    private function runMigration(): void
    {
        Artisan::call('migrate', ['--path' => self::PATH, '--force' => true]);
    }

    private function insertLegacyItem(Tenant $tenant, Quote $quote, int $position): void
    {
        $attributes = QuoteItem::factory()->make([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'position' => $position,
        ])->getAttributes();
        unset($attributes['billing_period']);

        DB::table('quote_items')->insert(array_merge($attributes, [
            'id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function test_backfill_gives_old_itemized_quotes_one_one_off_group_equal_to_their_total(): void
    {
        $tenant = Tenant::factory()->create();
        $this->rollbackMigration();

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'subtotal' => 100, 'vat_amount' => 23, 'total' => 123]);
        $this->insertLegacyItem($tenant, $quote, 0);
        $this->insertLegacyItem($tenant, $quote, 1);

        $this->runMigration();

        $quote = Quote::query()->findOrFail($quote->id);

        $this->assertEquals(
            [['billing_period' => 'one_off', 'subtotal' => '100.00', 'vat_amount' => '23.00', 'total' => '123.00']],
            $quote->period_totals,
        );
        $this->assertSame('123.00', $quote->total);
        $this->assertSame(['one_off', 'one_off'], DB::table('quote_items')->where('quote_id', $quote->id)->pluck('billing_period')->all());
    }

    public function test_backfill_leaves_itemized_quotes_without_items_and_document_quotes_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->rollbackMigration();

        $empty = Quote::factory()->create(['tenant_id' => $tenant->id]);
        $document = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $this->runMigration();

        $this->assertNull(Quote::query()->findOrFail($empty->id)->period_totals);
        $this->assertNull(Quote::query()->findOrFail($document->id)->period_totals);
    }
}
