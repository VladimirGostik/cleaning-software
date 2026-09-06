<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Events\QuoteExpired;
use App\Events\QuoteExpiring;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ExpireQuotesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_past_valid_until_becomes_expired_and_dispatches_event(): void
    {
        Event::fake([QuoteExpired::class]);
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->subDay()->toDateString()]);

        $this->artisan('app:expire-quotes')->assertExitCode(0);

        $quote->refresh();
        $this->assertSame(QuoteStatusEnum::Expired, $quote->status);
        Event::assertDispatched(QuoteExpired::class, fn (QuoteExpired $e) => $e->quoteId === $quote->id);
    }

    public function test_sent_past_valid_until_becomes_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->subDay()->toDateString()]);

        $this->artisan('app:expire-quotes');

        $quote->refresh();
        $this->assertSame(QuoteStatusEnum::Expired, $quote->status);
    }

    public function test_valid_until_today_is_not_expired(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->toDateString()]);

        $this->artisan('app:expire-quotes');

        $quote->refresh();
        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
    }

    public function test_accepted_quote_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->accepted()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->subDay()->toDateString()]);

        $this->artisan('app:expire-quotes');

        $quote->refresh();
        $this->assertSame(QuoteStatusEnum::Accepted, $quote->status);
    }

    public function test_document_kind_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->subDay()->toDateString()]);

        $this->artisan('app:expire-quotes');

        $quote->refresh();
        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
    }

    public function test_already_expired_quote_skipped(): void
    {
        Event::fake([QuoteExpired::class]);
        $tenant = Tenant::factory()->create();
        Quote::factory()->expired()->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:expire-quotes');

        Event::assertNotDispatched(QuoteExpired::class);
    }

    public function test_expires_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $quoteA = Quote::factory()->create(['tenant_id' => $tenantA->id, 'valid_until' => now()->subDay()->toDateString()]);
        $quoteB = Quote::factory()->create(['tenant_id' => $tenantB->id, 'valid_until' => now()->subDay()->toDateString()]);

        $this->artisan('app:expire-quotes');

        $quoteA->refresh();
        $quoteB->refresh();
        $this->assertSame(QuoteStatusEnum::Expired, $quoteA->status);
        $this->assertSame(QuoteStatusEnum::Expired, $quoteB->status);
    }

    public function test_dispatches_expiring_event_for_configured_notice_days(): void
    {
        Event::fake([QuoteExpiring::class]);
        $tenant = Tenant::factory()->create();
        $quote = Quote::factory()->sent()->create(['tenant_id' => $tenant->id, 'valid_until' => now()->addDays(7)->toDateString()]);

        $this->artisan('app:expire-quotes');

        Event::assertDispatched(QuoteExpiring::class, fn (QuoteExpiring $e) => $e->quoteId === $quote->id && $e->daysLeft === 7);
    }
}
