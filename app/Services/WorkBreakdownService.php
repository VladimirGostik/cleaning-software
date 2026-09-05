<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Schedule\WorkBreakdownUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\TaskFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\WorkBreakdown;
use App\Models\WorkBreakdownTask;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class WorkBreakdownService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * Auto-generate a WorkBreakdown from a signed ServiceAgreement contract backed by a quote.
     * Idempotent: returns null if breakdown already exists for the contract.
     * Called inside ContractService::sign() transaction.
     */
    public function generateFromContract(Contract $contract): ?WorkBreakdown
    {
        if ($contract->category !== ContractCategoryEnum::ServiceAgreement) {
            return null;
        }

        if (! ($contract->contractable instanceof CleaningObject)) {
            return null;
        }

        if ($contract->quote_id === null) {
            return null;
        }

        // Idempotency guard — skip if breakdown already exists for this contract.
        $existing = WorkBreakdown::where('contract_id', $contract->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        /** @var Quote $quote */
        $quote = Quote::with('items')->findOrFail($contract->quote_id);

        /** @var CleaningObject $cleaningObject */
        $cleaningObject = $contract->contractable;

        $breakdown = WorkBreakdown::create([
            'cleaning_object_id' => $cleaningObject->id,
            'contract_id' => $contract->id,
            'source_quote_id' => $quote->id,
            'name' => $cleaningObject->name . ' — ' . $contract->title,
            'is_active' => true,
        ]);

        foreach ($quote->items as $position => $item) {
            $frequency = $item->frequency !== null
                ? (TaskFrequencyEnum::tryFrom($item->frequency) ?? TaskFrequencyEnum::OneTime)
                : TaskFrequencyEnum::OneTime;

            WorkBreakdownTask::create([
                'work_breakdown_id' => $breakdown->id,
                'name' => $item->name,
                'description' => $item->description,
                'frequency' => $frequency->value,
                'position' => $position,
            ]);
        }

        return $breakdown->load('tasks');
    }

    /**
     * @return LengthAwarePaginator<WorkBreakdown>
     */
    public function paginate(string $cleaningObjectId, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WorkBreakdown::query()->where('cleaning_object_id', $cleaningObjectId))
            ->defaultSort('-created_at')
            ->with(['tasks', 'contract', 'sourceQuote'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function create(WorkBreakdownUpsertData $data): WorkBreakdown
    {
        return $this->db->transaction(function () use ($data): WorkBreakdown {
            $breakdown = WorkBreakdown::create($data->toArray());

            return $breakdown->load('tasks');
        });
    }

    public function update(WorkBreakdown $breakdown, WorkBreakdownUpsertData $data): WorkBreakdown
    {
        return $this->db->transaction(function () use ($breakdown, $data): WorkBreakdown {
            $breakdown->update($data->toArray());

            return $breakdown->refresh()->load('tasks');
        });
    }

    public function delete(WorkBreakdown $breakdown): void
    {
        $this->db->transaction(fn () => $breakdown->delete());
    }
}
