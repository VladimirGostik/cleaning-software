<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractListItemData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Events\ContractSigned;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ContractService
{
    public function __construct(
        private DatabaseManager $db,
        private PlaceholderResolverService $placeholders,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ContractListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Contract::query())
            ->allowedFilters(
                AllowedFilter::search(['title', 'number']),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('category'),
                AllowedFilter::dynamic('term_type'),
                AllowedFilter::dynamic('contractable_type'),
                AllowedFilter::dynamic('valid_from')->date(),
                AllowedFilter::dynamic('end_date')->date(),
            )
            ->allowedSorts('title', 'number', 'status', 'category', 'valid_from', 'end_date', 'created_at')
            ->defaultSort('-created_at')
            ->with(['contractable' => function (MorphTo|Relation $relation) {
                /** @var MorphTo<Model, Contract> $relation */
                return $relation->morphWith([
                    TenantMembership::class => ['user:id,name,email'],
                ]);
            }])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Contract $contract) => ContractListItemData::fromModel($contract));
    }

    public function create(ContractUpsertData $data, ?Quote $sourceQuote = null): Contract
    {
        $contractable = $this->resolveContractable($data);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);

        return $this->db->transaction(function () use ($data, $contractable, $sourceQuote): Contract {
            $tenant = Tenant::query()->findOrFail(current_tenant_id());

            $body = $this->placeholders->resolve(
                $data->body,
                $this->placeholders->variablesFor($contractable, $tenant, $data, $sourceQuote),
            );

            $contract = Contract::create([
                'contract_template_id' => $data->contract_template_id,
                'quote_id' => $sourceQuote?->id,
                'contractable_type' => $data->contractable_type->value,
                'contractable_id' => $data->contractable_id,
                'category' => $data->category,
                'status' => ContractStatusEnum::Draft,
                'term_type' => $data->term_type,
                'title' => $data->title,
                'number' => $data->number,
                'body' => $body,
                'valid_from' => $data->valid_from,
                'end_date' => $data->end_date,
                'notes' => $data->notes,
            ]);

            if ($data->employment !== null) {
                EmploymentContract::create([
                    'tenant_id' => $contract->tenant_id,
                    'contract_id' => $contract->id,
                    'employment_type' => $data->employment->employment_type,
                    'position' => $data->employment->position,
                    'hourly_rate' => $data->employment->hourly_rate,
                    'monthly_salary' => $data->employment->monthly_salary,
                    'weekly_hours' => $data->employment->weekly_hours,
                    'probation_end_date' => $data->employment->probation_end_date,
                ]);
            }

            return $contract->load(['employmentContract', 'contractTemplate']);
        });
    }

    public function update(Contract $contract, ContractUpsertData $data): Contract
    {
        if (! $contract->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_not_editable')]]);
        }

        $contractable = $this->resolveContractable($data);
        $this->assertCategoryMatchesContractable($data->category, $data->contractable_type);

        return $this->db->transaction(function () use ($contract, $data, $contractable): Contract {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id);

            $body = $this->placeholders->resolve(
                $data->body,
                $this->placeholders->variablesFor($contractable, $tenant, $data, $contract->quote),
            );

            $contract->update([
                'contract_template_id' => $data->contract_template_id,
                'contractable_type' => $data->contractable_type->value,
                'contractable_id' => $data->contractable_id,
                'category' => $data->category,
                'term_type' => $data->term_type,
                'title' => $data->title,
                'number' => $data->number,
                'body' => $body,
                'valid_from' => $data->valid_from,
                'end_date' => $data->end_date,
                'notes' => $data->notes,
            ]);

            if ($data->employment !== null) {
                $contract->employmentContract()->updateOrCreate([], [
                    'tenant_id' => $contract->tenant_id,
                    'employment_type' => $data->employment->employment_type,
                    'position' => $data->employment->position,
                    'hourly_rate' => $data->employment->hourly_rate,
                    'monthly_salary' => $data->employment->monthly_salary,
                    'weekly_hours' => $data->employment->weekly_hours,
                    'probation_end_date' => $data->employment->probation_end_date,
                ]);
            } else {
                $contract->employmentContract()->delete();
            }

            return $contract->load(['employmentContract', 'contractTemplate']);
        });
    }

    public function sign(Contract $contract): Contract
    {
        if (! $contract->canBeSigned()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_cannot_sign')]]);
        }

        return $this->db->transaction(function () use ($contract): Contract {
            $contract->update(['status' => ContractStatusEnum::Active, 'signed_at' => now()]);

            ContractSigned::dispatch($contract->tenant_id, $contract->id);

            return $contract;
        });
    }

    public function terminate(Contract $contract, ContractTerminateData $data): Contract
    {
        if (! $contract->canBeTerminated()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_cannot_terminate')]]);
        }

        return $this->db->transaction(function () use ($contract, $data): Contract {
            $contract->update([
                'status' => ContractStatusEnum::Terminated,
                'terminated_at' => $data->terminated_at,
                'termination_reason' => $data->termination_reason,
            ]);

            return $contract;
        });
    }

    public function delete(Contract $contract): void
    {
        if (! $contract->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.contract_not_editable')]]);
        }

        $this->db->transaction(function () use ($contract): void {
            $contract->delete();
        });
    }

    private function resolveContractable(ContractUpsertData $data): CleaningObject|TenantMembership
    {
        return match ($data->contractable_type) {
            ContractableTypeEnum::CleaningObject => CleaningObject::query()->findOrFail($data->contractable_id),
            ContractableTypeEnum::TenantMembership => TenantMembership::query()
                ->where('tenant_id', current_tenant_id())
                ->findOrFail($data->contractable_id),
        };
    }

    private function assertCategoryMatchesContractable(ContractCategoryEnum $category, ContractableTypeEnum $contractableType): void
    {
        $expected = $category->expectedContractableType();

        if ($expected !== null && $expected !== $contractableType) {
            throw ValidationException::withMessages([
                'contractable_type' => [__('app.contract_category_contractable_mismatch')],
            ]);
        }
    }
}
