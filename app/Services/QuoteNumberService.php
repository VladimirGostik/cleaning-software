<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteNumberSequence;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use DateTimeInterface;

final readonly class QuoteNumberService
{
    public function next(Tenant $tenant, DateTimeInterface $issueDate): string
    {
        $year = (int) $issueDate->format('Y');

        QuoteNumberSequence::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'year' => $year],
            ['last_number' => 0],
        );

        $sequence = QuoteNumberSequence::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        do {
            $sequence->increment('last_number');
            $sequence->refresh();

            $formatted = $this->format($issueDate, $sequence->last_number);

            $taken = Quote::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('number', $formatted)
                ->whereNull('deleted_at')
                ->exists();
        } while ($taken);

        return $formatted;
    }

    private function format(DateTimeInterface $date, int $sequence): string
    {
        return 'CP' . $date->format('Y') . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
