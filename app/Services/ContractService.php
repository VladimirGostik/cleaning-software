<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractIndexFilterData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ContractService
{
    public function __construct(
        private DatabaseManager $db,
        private PlaceholderResolverService $resolver,
    ) {}

    /**
     * @return LengthAwarePaginator<Contract>
     */
    public function paginate(ContractIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(Contract::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('term_type'),
                AllowedFilter::exact('contractable_type'),
            )
            ->allowedSorts(
                AllowedSort::field('title'),
                AllowedSort::field('valid_from'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            // @phpstan-ignore-next-line — Larastan cannot resolve MorphTo closure return type for morphWith()
            ->with(['contractable' => fn (MorphTo $q) => $q->morphWith([
                CleaningObject::class => [],
                TenantMembership::class => ['user:id,name,email'],
            ])])
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(ContractUpsertData $data): Contract
    {
        if ($data->term_type === ContractTermTypeEnum::Fixed && $data->end_date === null) {
            throw ValidationException::withMessages([
                'end_date' => [__('app.contracts.end_date_required_for_fixed')],
            ]);
        }

        if ($data->category === ContractCategoryEnum::Employment && $data->employment === null) {
            throw ValidationException::withMessages([
                'employment' => [__('app.contracts.employment_required')],
            ]);
        }

        return $this->db->transaction(function () use ($data): Contract {
            $contractable = $this->resolveContractable($data->contractable_type, $data->contractable_id);

            $body = $data->body;

            if ($data->contract_template_id !== null) {
                /** @var Tenant $tenant */
                $tenant = Tenant::find(app('current_tenant_id'));

                $variables = $contractable instanceof CleaningObject
                    ? $this->resolver->variablesForCleaningObject($contractable, $tenant)
                    : $this->resolver->variablesForMembership($contractable, $tenant);

                $body = $this->resolver->resolve($body, $variables);
            }

            $contract = Contract::create([
                ...$data->toArray(),
                'body' => $body,
                'status' => ContractStatusEnum::Draft,
            ]);

            if ($data->employment !== null) {
                EmploymentContract::create([
                    ...$data->employment->toArray(),
                    'contract_id' => $contract->id,
                ]);
            }

            return $contract->load(['employmentContract', 'contractTemplate']);
        });
    }

    public function update(Contract $contract, ContractUpsertData $data): Contract
    {
        throw_unless($contract->isEditable(), ValidationException::withMessages([
            'contract' => [__('app.contracts.not_editable')],
        ]));

        if ($data->term_type === ContractTermTypeEnum::Fixed && $data->end_date === null) {
            throw ValidationException::withMessages([
                'end_date' => [__('app.contracts.end_date_required_for_fixed')],
            ]);
        }

        if ($data->category === ContractCategoryEnum::Employment && $data->employment === null) {
            throw ValidationException::withMessages([
                'employment' => [__('app.contracts.employment_required')],
            ]);
        }

        return $this->db->transaction(function () use ($contract, $data): Contract {
            $contract->update($data->toArray());

            if ($data->employment !== null) {
                $contract->employmentContract()->updateOrCreate(
                    ['contract_id' => $contract->id],
                    $data->employment->toArray(),
                );
            } else {
                $contract->employmentContract()->delete();
            }

            return $contract->load(['employmentContract', 'contractTemplate']);
        });
    }

    public function sign(Contract $contract): Contract
    {
        throw_unless($contract->canBeSigned(), ValidationException::withMessages([
            'contract' => [__('app.contracts.cannot_sign')],
        ]));

        return $this->db->transaction(function () use ($contract): Contract {
            $contract->update([
                'status' => ContractStatusEnum::Active,
                'signed_at' => now(),
            ]);

            return $contract;
        });
    }

    public function terminate(Contract $contract, ContractTerminateData $data): Contract
    {
        throw_unless($contract->canBeTerminated(), ValidationException::withMessages([
            'contract' => [__('app.contracts.cannot_terminate')],
        ]));

        return $this->db->transaction(function () use ($contract, $data): Contract {
            $contract->update([
                'status' => ContractStatusEnum::Terminated,
                'terminated_at' => Carbon::parse($data->terminated_at),
                'termination_reason' => $data->termination_reason,
            ]);

            return $contract;
        });
    }

    public function delete(Contract $contract): void
    {
        throw_unless($contract->isEditable(), ValidationException::withMessages([
            'contract' => [__('app.contracts.not_editable')],
        ]));

        $this->db->transaction(fn () => $contract->delete());
    }

    private function resolveContractable(string $type, string $id): CleaningObject|TenantMembership
    {
        return match ($type) {
            'cleaning_object' => CleaningObject::findOrFail($id),
            'tenant_membership' => TenantMembership::findOrFail($id),
            default => throw new InvalidArgumentException("Unknown contractable type: {$type}"),
        };
    }
}
