<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PermissionEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use App\Notifications\QuoteExpired;
use App\Notifications\QuoteExpiring;
use App\Scopes\TenantScope;
use App\Services\NotificationRecipientResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class ExpireQuotes extends Command
{
    protected $signature = 'app:expire-quotes';

    protected $description = 'Mark Draft and Sent quotes whose valid_until date is in the past as Expired';

    public function handle(NotificationRecipientResolver $resolver): int
    {
        // Phase 1: Expire quotes past valid_until
        Quote::withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [QuoteStatusEnum::Draft->value, QuoteStatusEnum::Sent->value])
            ->whereDate('valid_until', '<', now()->toDateString())
            ->lazyById(500)
            ->each(function (Quote $quote) use ($resolver): void {
                $quote->update(['status' => QuoteStatusEnum::Expired]);

                logger()->info('quote.expired', [
                    'quote_id' => $quote->id,
                    'tenant_id' => $quote->tenant_id,
                    'valid_until' => $quote->valid_until->toDateString(),
                ]);

                $recipients = $resolver->usersWithPermission(
                    $quote->tenant_id,
                    PermissionEnum::ViewQuotes,
                );

                Notification::send($recipients, new QuoteExpired($quote->tenant_id, $quote->id));
            });

        // Phase 2: Notify for quotes expiring in exactly 7, 3, or 1 days
        foreach ([7, 3, 1] as $days) {
            $target = now()->addDays($days)->toDateString();

            Quote::withoutGlobalScope(TenantScope::class)
                ->whereIn('status', [QuoteStatusEnum::Draft->value, QuoteStatusEnum::Sent->value])
                ->whereDate('valid_until', $target)
                ->lazyById(200)
                ->each(function (Quote $quote) use ($days, $resolver): void {
                    $recipients = $resolver->usersWithPermission(
                        $quote->tenant_id,
                        PermissionEnum::ViewQuotes,
                    );

                    Notification::send($recipients, new QuoteExpiring($quote->tenant_id, $quote->id, $days));
                });
        }

        $this->info('Quote expiry check complete.');

        return self::SUCCESS;
    }
}
