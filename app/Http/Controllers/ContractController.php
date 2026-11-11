<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RendersContractPdf;
use App\Data\Contracts\ContractCreatePrefillData;
use App\Data\Contracts\ContractDetailData;
use App\Data\Contracts\ContractFileConstraintsData;
use App\Data\Contracts\ContractFormContextData;
use App\Data\Contracts\ContractGenerateData;
use App\Data\Contracts\ContractTerminateData;
use App\Data\Contracts\ContractUpsertData;
use App\Data\Contracts\MembershipOptionData;
use App\Data\DocumentTemplates\DocumentTemplateOptionData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\SignedPdfUnreadableException;
use App\Http\Controllers\Concerns\ProvidesSubjectOptions;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ContractService;
use App\Services\Documents\ContractFileService;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Log;
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
        private readonly ContractTextService $text,
        private readonly ContractFileService $files,
        private readonly DocumentTemplateService $templates,
        private readonly DocumentTemplateCatalogue $catalogue,
    ) {}

    #[Authorize('viewAny', Contract::class)]
    #[NavItem(label: 'app.contracts', route: 'contracts.index', icon: NavIconEnum::Contracts, permission: PermissionEnum::ViewContracts->value, order: 36)]
    public function index(Request $request): InertiaResponse
    {
        return Inertia::render('Contracts/Index', [
            'contracts' => $this->contracts->paginate($request, $this->actor($request)),
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function create(Request $request): InertiaResponse
    {
        $actor = $this->actor($request);

        return Inertia::render('Contracts/Create', [
            'context' => $this->formContext($actor),
            'prefill' => $this->prefill($request, $actor),
        ]);
    }

    #[Authorize('create', Contract::class)]
    public function store(ContractUpsertData $data, Request $request): RedirectResponse
    {
        $contract = $this->contracts->create($data, $this->actor($request));

        return to_route('contracts.show', $contract)->with('success', __('app.contract_created'));
    }

    #[Authorize('create', Contract::class)]
    public function generate(ContractGenerateData $data, Request $request): JsonResponse
    {
        return response()->json($this->contracts->generate($data, $this->actor($request))->toArray());
    }

    #[Authorize('create', Contract::class)]
    public function generatePdf(ContractGenerateData $data, Request $request): Response
    {
        return response($this->contracts->generatePdf($data, $this->actor($request)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contract.pdf"',
        ]);
    }

    #[Authorize('view', 'contract')]
    public function show(Contract $contract, Request $request): InertiaResponse
    {
        Inertia::encryptHistory();

        $detail = ContractDetailData::fromModel($contract, $this->text, $this->files, $this->actor($request));

        return Inertia::render('Contracts/Show', [
            'contract' => $detail,
            'fileConstraints' => $detail->can_manage_files ? ContractFileConstraintsData::fromConfig() : null,
        ]);
    }

    #[Authorize('update', 'contract')]
    public function edit(Contract $contract, Request $request): InertiaResponse
    {
        Inertia::encryptHistory();

        $keepObjectId = $contract->contractable_type === ContractableTypeEnum::CleaningObject->value
            ? $contract->contractable_id
            : null;

        $actor = $this->actor($request);

        return Inertia::render('Contracts/Edit', [
            'contract' => ContractDetailData::fromModel($contract, $this->text, $this->files, $actor),
            'context' => $this->formContext($actor, $keepObjectId),
        ]);
    }

    #[Authorize('update', 'contract')]
    public function update(ContractUpsertData $data, Contract $contract, Request $request): RedirectResponse
    {
        $this->contracts->update($contract, $data, $this->actor($request));

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
    public function pdf(Contract $contract, RendersContractPdf $pdfService): Response|RedirectResponse
    {
        try {
            $pdfContent = $pdfService->render($contract);
        } catch (SignedPdfUnreadableException $e) {
            Log::error('contract.signed_pdf.unreadable', ['contract_id' => $contract->id, 'media_id' => $e->mediaId]);

            return back()->with('error', __('app.contract_signed_pdf_unreadable'));
        }

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

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function formContext(User $actor, ?string $keepObjectId = null): ContractFormContextData
    {
        $memberships = [];

        if ($actor->can(PermissionEnum::ViewEmployeePersonalData->value)) {
            $memberships = TenantMembership::query()
                ->with('user:id,name,email')
                ->where('tenant_id', current_tenant_id())
                ->where('is_active', true)
                ->get()
                ->map(fn (TenantMembership $membership) => MembershipOptionData::fromModel($membership))
                ->sortBy('label')
                ->values()
                ->all();
        }

        $templates = [];

        foreach (ContractCategoryEnum::cases() as $category) {
            foreach ($this->templates->options(DocumentTypeEnum::fromCategory($category)) as $template) {
                $templates[] = DocumentTemplateOptionData::fromModel($template);
            }
        }

        $tenant = Tenant::query()->findOrFail(current_tenant_id());

        return new ContractFormContextData(
            objects: $this->objectOptions($keepObjectId),
            clients: $this->clientOptions(),
            memberships: $memberships,
            templates: $templates,
            tokens: $this->catalogue->catalog(),
            letterhead: DocumentFrame::letterhead($tenant),
            footer: DocumentFrame::footer($tenant),
            file_constraints: ContractFileConstraintsData::fromConfig(),
        );
    }

    private function prefill(Request $request, User $actor): ContractCreatePrefillData
    {
        $type = ContractableTypeEnum::tryFrom($request->string('contractable_type')->toString());
        $id = $request->string('contractable_id')->toString();
        $label = null;

        if ($type !== null && Str::isUuid($id)) {
            $label = $this->subjectLabel($type, $id, $actor);
        }

        if ($label === null) {
            $type = null;
            $id = null;
        }

        $category = ContractCategoryEnum::tryFrom($request->string('category')->toString());

        if ($category !== null && $type !== null && ! in_array($type, $category->allowedContractableTypes(), true)) {
            $category = null;
        }

        $employmentType = $category === ContractCategoryEnum::Employment
            ? EmploymentContractTypeEnum::tryFrom($request->string('employment_type')->toString())
            : null;

        $templateId = $request->string('document_template_id')->toString();
        $validTemplate = null;

        if ($category !== null && Str::isUuid($templateId)) {
            $validTemplate = collect($this->templates->options(DocumentTypeEnum::fromCategory($category), $employmentType))
                ->firstWhere('id', $templateId)?->id;
        }

        return new ContractCreatePrefillData(
            contractable_type: $type,
            contractable_id: $id,
            contractable_label: $label,
            category: $category,
            employment_type: $employmentType,
            document_template_id: $validTemplate,
        );
    }

    private function subjectLabel(ContractableTypeEnum $type, string $id, User $actor): ?string
    {
        return match ($type) {
            ContractableTypeEnum::CleaningObject => CleaningObject::query()->find($id)?->name,
            ContractableTypeEnum::Client => Client::query()->find($id)?->name,
            ContractableTypeEnum::TenantMembership => $actor->can(PermissionEnum::ViewEmployeePersonalData->value)
                ? TenantMembership::query()->with('user:id,name,email')->where('tenant_id', current_tenant_id())->find($id)?->display_name
                : null,
        };
    }
}
