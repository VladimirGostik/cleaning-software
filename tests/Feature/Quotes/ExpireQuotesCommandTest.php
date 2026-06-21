<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ExpireQuotesCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Draft past valid_until → Expired
    // -------------------------------------------------------------------------

    public function test_draft_quote_past_valid_until_is_marked_expired(): void
    {
        $quote = $this->makeQuote(QuoteStatusEnum::Draft, now()->subDay()->toDateString());

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatusEnum::Expired->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sent past valid_until → Expired
    // -------------------------------------------------------------------------

    public function test_sent_quote_past_valid_until_is_marked_expired(): void
    {
        $quote = $this->makeQuote(QuoteStatusEnum::Sent, now()->subDay()->toDateString());

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatusEnum::Expired->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Log emitted
    // -------------------------------------------------------------------------

    public function test_expiry_log_is_emitted(): void
    {
        Log::spy();

        $quote = $this->makeQuote(QuoteStatusEnum::Draft, now()->subDay()->toDateString());

        $this->artisan('app:expire-quotes');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'quote.expired'
                && $ctx['quote_id'] === $quote->id);
    }

    // -------------------------------------------------------------------------
    // valid_until = today → NOT expired yet
    // -------------------------------------------------------------------------

    public function test_quote_expiring_today_is_not_yet_expired(): void
    {
        $quote = $this->makeQuote(QuoteStatusEnum::Draft, now()->toDateString());

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatusEnum::Draft->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Accepted/Rejected are terminal — skip
    // -------------------------------------------------------------------------

    public function test_accepted_quote_is_not_expired_by_command(): void
    {
        $quote = $this->makeQuote(QuoteStatusEnum::Accepted, now()->subDay()->toDateString());

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatusEnum::Accepted->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Already Expired → idempotent
    // -------------------------------------------------------------------------

    public function test_already_expired_quote_is_not_reprocessed(): void
    {
        $quote = $this->makeQuote(QuoteStatusEnum::Expired, now()->subDay()->toDateString());

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => QuoteStatusEnum::Expired->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Cross-tenant — both processed
    // -------------------------------------------------------------------------

    public function test_cross_tenant_expiry_runs_for_all_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $quoteA = Quote::factory()->create([
            'tenant_id' => $tenantA->id,
            'client_id' => $clientA->id,
            'status' => QuoteStatusEnum::Draft,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $tenantB = Tenant::factory()->create();
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $quoteB = Quote::factory()->create([
            'tenant_id' => $tenantB->id,
            'client_id' => $clientB->id,
            'status' => QuoteStatusEnum::Sent,
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('app:expire-quotes')->assertSuccessful();

        $this->assertDatabaseHas('quotes', ['id' => $quoteA->id, 'status' => QuoteStatusEnum::Expired->value]);
        $this->assertDatabaseHas('quotes', ['id' => $quoteB->id, 'status' => QuoteStatusEnum::Expired->value]);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function makeQuote(QuoteStatusEnum $status, string $validUntil): Quote
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return Quote::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'status' => $status,
            'valid_until' => $validUntil,
        ]);
    }
}
