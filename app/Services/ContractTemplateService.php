<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractTemplates\ContractTemplateListItemData;
use App\Data\ContractTemplates\ContractTemplateUpsertData;
use App\Models\ContractTemplate;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ContractTemplateService
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return LengthAwarePaginator<int, ContractTemplateListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(ContractTemplate::query())
            ->allowedFilters(
                AllowedFilter::search(['name']),
                AllowedFilter::dynamic('category'),
                AllowedFilter::dynamic('is_active')->boolean(),
            )
            ->allowedSorts('name', 'category', 'is_active', 'updated_at')
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (ContractTemplate $template) => ContractTemplateListItemData::fromModel($template));
    }

    public function create(ContractTemplateUpsertData $data): ContractTemplate
    {
        return $this->db->transaction(fn () => ContractTemplate::create([
            'name' => $data->name,
            'category' => $data->category,
            'body' => $data->body,
            'is_active' => $data->is_active,
        ]));
    }

    public function update(ContractTemplate $template, ContractTemplateUpsertData $data): ContractTemplate
    {
        $this->db->transaction(function () use ($template, $data): void {
            $template->update([
                'name' => $data->name,
                'category' => $data->category,
                'body' => $data->body,
                'is_active' => $data->is_active,
            ]);
        });

        return $template;
    }

    public function delete(ContractTemplate $template): void
    {
        $this->db->transaction(function () use ($template): void {
            $template->delete();
        });
    }
}
