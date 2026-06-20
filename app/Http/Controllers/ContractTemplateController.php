<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ContractTemplates\ContractTemplateDetailData;
use App\Data\ContractTemplates\ContractTemplateIndexFilterData;
use App\Data\ContractTemplates\ContractTemplateListItemData;
use App\Data\ContractTemplates\ContractTemplateStoreData;
use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use App\Services\ContractTemplateService;
use App\Services\PlaceholderResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

final class ContractTemplateController extends Controller
{
    public function __construct(
        private readonly ContractTemplateService $service,
        private readonly PlaceholderResolverService $resolver,
    ) {}

    #[Authorize('viewAny', ContractTemplate::class)]
    public function index(ContractTemplateIndexFilterData $filter): Response
    {
        $paginator = $this->service->paginate($filter);

        return Inertia::render('ContractTemplates/Index', [
            'templates' => ContractTemplateListItemData::collect(
                $paginator->through(fn (ContractTemplate $t) => ContractTemplateListItemData::fromModel($t)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'categoryOptions' => ContractCategoryEnum::options(),
        ]);
    }

    #[Authorize('create', ContractTemplate::class)]
    public function create(): Response
    {
        return Inertia::render('ContractTemplates/Create', [
            'categoryOptions' => ContractCategoryEnum::options(),
            'clientContractTokens' => $this->resolver->catalogFor('cleaning_object'),
            'employmentContractTokens' => $this->resolver->catalogFor('tenant_membership'),
        ]);
    }

    #[Authorize('create', ContractTemplate::class)]
    public function store(ContractTemplateStoreData $data): RedirectResponse
    {
        $template = $this->service->create($data);

        return to_route('contract-templates.show', $template)
            ->with('flash.success', __('app.contract_templates.created'));
    }

    #[Authorize('view', 'contractTemplate')]
    public function show(ContractTemplate $contractTemplate): Response
    {
        return Inertia::render('ContractTemplates/Show', [
            'template' => ContractTemplateDetailData::fromModel($contractTemplate),
        ]);
    }

    #[Authorize('update', 'contractTemplate')]
    public function edit(ContractTemplate $contractTemplate): Response
    {
        return Inertia::render('ContractTemplates/Edit', [
            'template' => ContractTemplateDetailData::fromModel($contractTemplate),
            'categoryOptions' => ContractCategoryEnum::options(),
            'clientContractTokens' => $this->resolver->catalogFor('cleaning_object'),
            'employmentContractTokens' => $this->resolver->catalogFor('tenant_membership'),
        ]);
    }

    #[Authorize('update', 'contractTemplate')]
    public function update(ContractTemplateStoreData $data, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->service->update($contractTemplate, $data);

        return to_route('contract-templates.show', $contractTemplate)
            ->with('flash.success', __('app.contract_templates.updated'));
    }

    #[Authorize('delete', 'contractTemplate')]
    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->service->delete($contractTemplate);

        return to_route('contract-templates.index')
            ->with('flash.success', __('app.contract_templates.deleted'));
    }
}
