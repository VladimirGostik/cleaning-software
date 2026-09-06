<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Events\QuoteExpired;
use App\Events\QuoteExpiring;
use App\Models\Quote;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;

final class ExpireQuotes extends Command
{
    protected $signature = 'app:expire-quotes';

    protected $description = 'Expire itemized quotes past their validity date and notify about upcoming expiry';

    public function handle(): int
    {
        Quote::withoutGlobalScope(TenantScope::class)
            ->where('kind', QuoteKindEnum::Itemized->value)
            ->whereIn('status', [QuoteStatusEnum::Draft->value, QuoteStatusEnum::Sent->value])
            ->whereDate('valid_until', '<', today())
            ->lazyById(500)
            ->each(function (Quote $quote): void {
                $quote->update(['status' => QuoteStatusEnum::Expired]);

                logger()->info('quote.expired', ['quote_id' => $quote->id, 'tenant_id' => $quote->tenant_id]);

                QuoteExpired::dispatch($quote->tenant_id, $quote->id);
            });

        /** @var list<mixed> $noticeDays */
        $noticeDays = (array) config('quotes.expiring_notice_days', []);

        foreach ($noticeDays as $rawDays) {
            $days = is_numeric($rawDays) ? (int) $rawDays : 0;

            Quote::withoutGlobalScope(TenantScope::class)
                ->where('kind', QuoteKindEnum::Itemized->value)
                ->whereIn('status', [QuoteStatusEnum::Draft->value, QuoteStatusEnum::Sent->value])
                ->whereDate('valid_until', today()->addDays($days))
                ->lazyById(500)
                ->each(function (Quote $quote) use ($days): void {
                    QuoteExpiring::dispatch($quote->tenant_id, $quote->id, $days);
                });
        }

        $this->info('Quotes expired.');

        return self::SUCCESS;
    }
}
