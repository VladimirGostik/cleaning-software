<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersContractPdf;
use App\Data\Contracts\ContractDetailData;
use App\Data\Contracts\ContractIndexFilterData;
use App\Data\Contracts\ContractListItemData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Data\Contracts\MembershipOptionData;
use App\Data\ContractTemplates\ContractTemplateListItemData;
use App\Data\Objects\ObjectOptionData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\TenantMembership;
use App\Services\ContractService;
use App\Services\PlaceholderResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $service,
        private readonly PlaceholderResolverService $resolver,
        private readonly RendersContractPdf $pdfService,
    ) {}

    #[Authorize('viewAny', Contract::class)]
    public function index(ContractIndexFilterData $filter): Response
    {
        $paginator = $this->service->paginate($filter);

        return Inertia::render('Contracts/Index', [
            'contracts' => ContractListItemData::collect(
                $paginator->through(fn (Contract $c) => ContractListItemData::fromModel($c)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'statusOptions' => ContractStatusEnum::options(),
            'categoryOptions' => ContractCategoryEnum::options(),
            'termTypeOptions' => ContractTermTypeEnum::options(),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function create(): Response
    {
        $tenantId = app('current_tenant_id');

        return Inertia::render('Contracts/Create', [
            'activeTemplates' => ContractTemplateListItemData::collect(
                ContractTemplate::active()->get()
                    ->map(fn (ContractTemplate $t) => ContractTemplateListItemData::fromModel($t)),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->where('is_active', true)->orderBy('name')->get(),
                DataCollection::class,
            ),
            'memberships' => MembershipOptionData::collect(
                TenantMembership::with('user:id,name,email')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                DataCollection::class,
            ),
            'categoryOptions' => ContractCategoryEnum::options(),
            'termTypeOptions' => ContractTermTypeEnum::options(),
            'employmentTypeOptions' => EmploymentContractTypeEnum::options(),
            'clientContractTokens' => $this->resolver->catalogFor('cleaning_object'),
            'employmentContractTokens' => $this->resolver->catalogFor('tenant_membership'),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function store(ContractUpsertData $data): RedirectResponse
    {
        $contract = $this->service->create($data);

        return to_route('contracts.show', $contract)
            ->with('flash.success', __('app.contracts.created'));
    }

    #[Authorize('view', 'contract')]
    public function show(Contract $contract): Response
    {
        return Inertia::render('Contracts/Show', [
            'contract' => ContractDetailData::fromModel($contract),
        ]);
    }

    #[Authorize('update', 'contract')]
    public function edit(Contract $contract): Response
    {
        $tenantId = app('current_tenant_id');

        return Inertia::render('Contracts/Edit', [
            'contract' => ContractDetailData::fromModel($contract),
            'activeTemplates' => ContractTemplateListItemData::collect(
                ContractTemplate::active()->get()
                    ->map(fn (ContractTemplate $t) => ContractTemplateListItemData::fromModel($t)),
                DataCollection::class,
            ),
            'objects' => ObjectOptionData::collect(
                CleaningObject::query()->select(['id', 'name', 'client_id'])->where('is_active', true)->orderBy('name')->get(),
                DataCollection::class,
            ),
            'memberships' => MembershipOptionData::collect(
                TenantMembership::with('user:id,name,email')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                DataCollection::class,
            ),
            'categoryOptions' => ContractCategoryEnum::options(),
            'termTypeOptions' => ContractTermTypeEnum::options(),
            'employmentTypeOptions' => EmploymentContractTypeEnum::options(),
            'clientContractTokens' => $this->resolver->catalogFor('cleaning_object'),
            'employmentContractTokens' => $this->resolver->catalogFor('tenant_membership'),
        ]);
    }

    #[Authorize('update', 'contract')]
    public function update(ContractUpsertData $data, Contract $contract): RedirectResponse
    {
        $this->service->update($contract, $data);

        return to_route('contracts.show', $contract)
            ->with('flash.success', __('app.contracts.updated'));
    }

    #[Authorize('delete', 'contract')]
    public function destroy(Contract $contract): RedirectResponse
    {
        $this->service->delete($contract);

        return to_route('contracts.index')
            ->with('flash.success', __('app.contracts.deleted'));
    }

    #[Authorize('sign', 'contract')]
    public function sign(Contract $contract): RedirectResponse
    {
        $this->service->sign($contract);

        return to_route('contracts.show', $contract)
            ->with('flash.success', __('app.contracts.signed'));
    }

    #[Authorize('terminate', 'contract')]
    public function terminate(ContractTerminateData $data, Contract $contract): RedirectResponse
    {
        $this->service->terminate($contract, $data);

        return to_route('contracts.show', $contract)
            ->with('flash.success', __('app.contracts.terminated'));
    }

    #[Authorize('downloadPdf', 'contract')]
    public function pdf(Contract $contract): StreamedResponse
    {
        $bytes = $this->pdfService->render($contract);
        $filename = ($contract->reference_number ?? $contract->id) . '.pdf';

        return response()->streamDownload(
            fn () => print ($bytes),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
