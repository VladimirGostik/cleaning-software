<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\InvoiceNumberSequence;
use App\Models\Tenant;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceNumberContinueSeriesTest extends TestCase
{
    use RefreshDatabase;

    private function lastNumber(Tenant $tenant, int $year): ?int
    {
        $value = InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('year', $year)->value('last_number');

        return is_int($value) ? $value : null;
    }

    public function test_missing_sequence_is_created_at_the_legacy_number(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue(app(InvoiceNumberService::class)->continueSeries($tenant, 2026, 41));
        $this->assertSame(41, $this->lastNumber($tenant, 2026));
    }

    public function test_lower_sequence_is_raised(): void
    {
        $tenant = Tenant::factory()->create();
        InvoiceNumberSequence::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'year' => 2026, 'last_number' => 3]);

        $this->assertTrue(app(InvoiceNumberService::class)->continueSeries($tenant, 2026, 41));
        $this->assertSame(41, $this->lastNumber($tenant, 2026));
    }

    public function test_higher_or_equal_sequence_is_left_alone(): void
    {
        $tenant = Tenant::factory()->create();
        InvoiceNumberSequence::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'year' => 2026, 'last_number' => 50]);

        $this->assertFalse(app(InvoiceNumberService::class)->continueSeries($tenant, 2026, 41));
        $this->assertFalse(app(InvoiceNumberService::class)->continueSeries($tenant, 2026, 50));
        $this->assertSame(50, $this->lastNumber($tenant, 2026));
    }

    public function test_next_number_continues_after_the_legacy_one(): void
    {
        $tenant = Tenant::factory()->create(['invoice_number_format' => '{YYYY}{XXXX}']);
        app(InvoiceNumberService::class)->continueSeries($tenant, 2026, 41);

        $this->assertSame('20260042', app(InvoiceNumberService::class)->next($tenant, now()->setDate(2026, 11, 5)));
    }

    public function test_default_format_is_adopted_and_a_custom_one_is_kept(): void
    {
        $default = Tenant::factory()->create(['invoice_number_format' => config()->string('invoicing.default_number_format')]);
        $custom = Tenant::factory()->create(['invoice_number_format' => 'INV{XXXX}']);
        $settings = app(InvoiceSettingsService::class);

        $this->assertTrue($settings->adoptNumberFormat($default, '{YYYY}{XXXX}'));
        $this->assertFalse($settings->adoptNumberFormat($custom, '{YYYY}{XXXX}'));
        $this->assertSame('{YYYY}{XXXX}', $default->refresh()->invoice_number_format);
        $this->assertSame('INV{XXXX}', $custom->refresh()->invoice_number_format);
    }
}
