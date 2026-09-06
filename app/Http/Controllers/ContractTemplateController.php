<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ContractTemplates\ContractTemplateDetailData;
use App\Data\ContractTemplates\ContractTemplateUpsertData;
use App\Enums\PermissionEnum;
use App\Models\ContractTemplate;
use App\Navigation\NavItem;
use App\Services\ContractTemplateService;
use App\Services\PlaceholderResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ContractTemplateController extends Controller
{
    public function __construct(
        private readonly ContractTemplateService $templates,
        private readonly PlaceholderResolverService $placeholders,
    ) {}

    #[Authorize('viewAny', ContractTemplate::class)]
    #[NavItem(label: 'app.contract_templates', route: 'contract-templates.index', icon: 'RectangleStackIcon', permission: PermissionEnum::ViewContractTemplates->value, group: 'settings', order: 30)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('ContractTemplates/Index', [
            'templates' => $this->templates->paginate($request),
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('create', ContractTemplate::class)]
    public function create(): InertiaResponse
    {
        return Inertia::render('ContractTemplates/Create', [
            'tokens' => $this->placeholders->catalog(),
        ]);
    }

    #[Authorize('create', ContractTemplate::class)]
    public function store(ContractTemplateUpsertData $data): RedirectResponse
    {
        $template = $this->templates->create($data);

        return to_route('contract-templates.show', $template)->with('success', __('app.contract_template_created'));
    }

    #[Authorize('view', 'contractTemplate')]
    public function show(ContractTemplate $contractTemplate): InertiaResponse
    {
        return Inertia::render('ContractTemplates/Show', [
            'template' => ContractTemplateDetailData::fromModel($contractTemplate),
        ]);
    }

    #[Authorize('update', 'contractTemplate')]
    public function edit(ContractTemplate $contractTemplate): InertiaResponse
    {
        return Inertia::render('ContractTemplates/Edit', [
            'template' => ContractTemplateDetailData::fromModel($contractTemplate),
            'tokens' => $this->placeholders->catalog(),
        ]);
    }

    #[Authorize('update', 'contractTemplate')]
    public function update(ContractTemplateUpsertData $data, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->templates->update($contractTemplate, $data);

        return to_route('contract-templates.show', $contractTemplate)->with('success', __('app.contract_template_updated'));
    }

    #[Authorize('delete', 'contractTemplate')]
    public function destroy(ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->templates->delete($contractTemplate);

        return to_route('contract-templates.index')->with('success', __('app.contract_template_deleted'));
    }
}
