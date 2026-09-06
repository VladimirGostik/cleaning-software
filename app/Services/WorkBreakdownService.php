<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContractCategoryEnum;
use App\Enums\TaskFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use App\Scopes\TenantScope;
use Illuminate\Database\DatabaseManager;

final readonly class WorkBreakdownService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * Generates a work breakdown (+ per-item tasks) from a signed service-agreement contract.
     * Idempotent — returns the existing breakdown when one already exists for the contract.
     * Returns null when the contract is not eligible (not a service agreement, subject is not
     * a cleaning object, or the contract has no source quote).
     */
    public function generateFromContract(Contract $contract): ?WorkBreakdown
    {
        if ($contract->category !== ContractCategoryEnum::ServiceAgreement) {
            return null;
        }

        if (! $contract->contractable instanceof CleaningObject) {
            return null;
        }

        if ($contract->quote_id === null) {
            return null;
        }

        /** @var WorkBreakdown|null $existing */
        $existing = WorkBreakdown::withoutGlobalScope(TenantScope::class)
            ->where('contract_id', $contract->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->db->transaction(function () use ($contract): WorkBreakdown {
            /** @var Quote $quote */
            $quote = Quote::withoutGlobalScope(TenantScope::class)
                ->with('items')
                ->findOrFail($contract->quote_id);

            $object = $contract->contractable;

            $breakdown = WorkBreakdown::create([
                'tenant_id' => $contract->tenant_id,
                'cleaning_object_id' => $object->id,
                'contract_id' => $contract->id,
                'source_quote_id' => $quote->id,
                'name' => $object->name.' — '.$contract->title,
                'is_active' => true,
            ]);

            /** @var QuoteItem $item */
            foreach ($quote->items->sortBy('position')->values() as $index => $item) {
                WorkBreakdownTask::create([
                    'tenant_id' => $contract->tenant_id,
                    'work_breakdown_id' => $breakdown->id,
                    'name' => $item->description,
                    'description' => $item->note,
                    'frequency' => $item->frequency ?? TaskFrequencyEnum::OneTime,
                    'position' => $index,
                ]);
            }

            return $breakdown->load('tasks');
        });
    }
}
