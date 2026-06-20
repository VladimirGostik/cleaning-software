<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractTemplates\ContractTemplateIndexFilterData;
use App\Data\ContractTemplates\ContractTemplateStoreData;
use App\Models\ContractTemplate;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ContractTemplateService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return LengthAwarePaginator<ContractTemplate>
     */
    public function paginate(ContractTemplateIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(ContractTemplate::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(ContractTemplateStoreData $data): ContractTemplate
    {
        return $this->db->transaction(fn (): ContractTemplate => ContractTemplate::create($data->toArray()));
    }

    public function update(ContractTemplate $template, ContractTemplateStoreData $data): ContractTemplate
    {
        return $this->db->transaction(function () use ($template, $data): ContractTemplate {
            $template->update($data->toArray());

            return $template;
        });
    }

    public function delete(ContractTemplate $template): void
    {
        $this->db->transaction(fn () => $template->delete());
    }
}
