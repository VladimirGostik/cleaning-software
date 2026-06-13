<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use DateTimeInterface;

final readonly class InvoiceNumberService
{
    public function next(Tenant $tenant, DateTimeInterface $issueDate): string
    {
        $year = (int) $issueDate->format('Y');

        // firstOrCreate then re-read with lockForUpdate — pessimistic lock per D5
        InvoiceNumberSequence::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'year' => $year],
            ['last_number' => 0],
        );

        $sequence = InvoiceNumberSequence::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        do {
            $sequence->increment('last_number');
            $sequence->refresh();

            $formatted = $this->format($tenant->invoice_number_format, $issueDate, $sequence->last_number);

            $taken = Invoice::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('number', $formatted)
                ->whereNull('deleted_at')
                ->exists();
        } while ($taken);

        return $formatted;
    }

    public function variableSymbol(string $number): ?string
    {
        $digits = preg_replace('/\D/', '', $number);

        return $digits !== '' && $digits !== null ? $digits : null;
    }

    private function format(string $format, DateTimeInterface $date, int $sequence): string
    {
        // Replace year/month placeholders
        $result = str_replace(
            ['{YYYY}', '{YY}', '{MM}'],
            [$date->format('Y'), $date->format('y'), $date->format('m')],
            $format,
        );

        // Replace sequence placeholder {X+} — pad to placeholder length
        $result = preg_replace_callback(
            '/\{(X+)\}/',
            fn (array $m) => str_pad((string) $sequence, strlen($m[1]), '0', STR_PAD_LEFT),
            $result,
        );

        return (string) $result;
    }
}
