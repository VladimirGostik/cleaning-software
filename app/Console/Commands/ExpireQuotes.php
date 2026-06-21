<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class ExpireQuotes extends Command
{
    protected $signature = 'app:expire-quotes';

    protected $description = 'Mark Draft and Sent quotes whose valid_until date is in the past as Expired';

    public function handle(): int
    {
        Quote::withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [QuoteStatusEnum::Draft->value, QuoteStatusEnum::Sent->value])
            ->whereDate('valid_until', '<', now()->toDateString())
            ->lazyById(500)
            ->each(function (Quote $quote): void {
                $quote->update(['status' => QuoteStatusEnum::Expired]);

                logger()->info('quote.expired', [
                    'quote_id' => $quote->id,
                    'tenant_id' => $quote->tenant_id,
                    'valid_until' => $quote->valid_until->toDateString(),
                ]);
            });

        $this->info('Quote expiry check complete.');

        return self::SUCCESS;
    }
}
