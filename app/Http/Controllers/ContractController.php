<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersContractPdf;
use App\Data\Contracts\ContractDetailData;
use App\Data\Contracts\ContractFormContextData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Data\Contracts\MembershipOptionData;
use App\Data\ContractTemplates\ContractTemplateOptionData;
use App\Enums\ContractableTypeEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Concerns\ProvidesSubjectOptions;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\TenantMembership;
use App\Navigation\NavItem;
use App\Services\ContractService;
use App\Services\PlaceholderResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class ContractController extends Controller
{
    use ProvidesSubjectOptions;

    public function __construct(
        private readonly ContractService $contracts,
        private readonly PlaceholderResolverService $placeholders,
    ) {}

    #[Authorize('viewAny', Contract::class)]
    #[NavItem(label: 'app.contracts', route: 'contracts.index', icon: 'DocumentCheckIcon', permission: PermissionEnum::ViewContracts->value, order: 36)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Contracts/Index', [
            'contracts' => $this->contracts->paginate($request),
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function create(): InertiaResponse
    {
        return Inertia::render('Contracts/Create', [
            'context' => $this->formContext(),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function store(ContractUpsertData $data): RedirectResponse
    {
        $contract = $this->contracts->create($data);

        return to_route('contracts.show', $contract)->with('success', __('app.contract_created'));
    }

    #[Authorize('view', 'contract')]
    public function show(Contract $contract): InertiaResponse
    {
        return Inertia::render('Contracts/Show', [
            'contract' => ContractDetailData::fromModel($contract),
        ]);
    }

    #[Authorize('update', 'contract')]
    public function edit(Contract $contract): InertiaResponse
    {
        $keepObjectId = $contract->contractable_type === ContractableTypeEnum::CleaningObject->value
            ? $contract->contractable_id
            : null;

        return Inertia::render('Contracts/Edit', [
            'contract' => ContractDetailData::fromModel($contract),
            'context' => $this->formContext($keepObjectId),
        ]);
    }

    #[Authorize('update', 'contract')]
    public function update(ContractUpsertData $data, Contract $contract): RedirectResponse
    {
        $this->contracts->update($contract, $data);

        return to_route('contracts.show', $contract)->with('success', __('app.contract_updated'));
    }

    #[Authorize('delete', 'contract')]
    public function destroy(Contract $contract): RedirectResponse
    {
        $this->contracts->delete($contract);

        return to_route('contracts.index')->with('success', __('app.contract_deleted'));
    }

    #[Authorize('sign', 'contract')]
    public function sign(Contract $contract): RedirectResponse
    {
        $this->contracts->sign($contract);

        return to_route('contracts.show', $contract)->with('success', __('app.contract_signed'));
    }

    #[Authorize('terminate', 'contract')]
    public function terminate(ContractTerminateData $data, Contract $contract): RedirectResponse
    {
        $this->contracts->terminate($contract, $data);

        return to_route('contracts.show', $contract)->with('success', __('app.contract_terminated'));
    }

    #[Authorize('downloadPdf', 'contract')]
    public function pdf(Contract $contract, RendersContractPdf $pdfService): Response
    {
        $pdfContent = $pdfService->render($contract);

        $filename = $contract->pdfFilenameBase().'.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                Str::ascii($filename),
            ),
        ]);
    }

    private function formContext(?string $keepObjectId = null): ContractFormContextData
    {
        $memberships = TenantMembership::query()
            ->with('user:id,name,email')
            ->where('tenant_id', current_tenant_id())
            ->where('is_active', true)
            ->get()
            ->map(fn (TenantMembership $membership) => MembershipOptionData::fromModel($membership))
            ->sortBy('label')
            ->values()
            ->all();

        $templates = ContractTemplate::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (ContractTemplate $template) => ContractTemplateOptionData::fromModel($template))
            ->all();

        return new ContractFormContextData(
            objects: $this->objectOptions($keepObjectId),
            memberships: $memberships,
            templates: $templates,
            tokens: $this->placeholders->catalog(),
        );
    }
}
