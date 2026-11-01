<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\DocumentTemplates\DocumentTemplateEditData;
use App\Data\DocumentTemplates\DocumentTemplatePreviewData;
use App\Data\DocumentTemplates\DocumentTemplateUpsertData;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

final class DocumentTemplateController extends Controller
{
    public function __construct(
        private readonly DocumentTemplateService $templates,
        private readonly DocumentTemplateCatalogue $catalogue,
    ) {}

    #[Authorize('viewAny', DocumentTemplate::class)]
    #[NavItem(label: 'app.document_templates', route: 'document-templates.index', icon: 'RectangleStackIcon', permission: PermissionEnum::ViewDocumentTemplates->value, group: 'settings', order: 30)]
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('DocumentTemplates/Index', [
            'templates' => $this->templates->paginate($request),
            'filters' => $request->query(),
            'canCreateGlobal' => $user->can('createGlobal', DocumentTemplate::class),
        ]);
    }

    #[Authorize('create', DocumentTemplate::class)]
    public function create(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $this->tenant();

        $type = DocumentTypeEnum::tryFrom($request->string('type')->toString());
        $employmentType = EmploymentContractTypeEnum::tryFrom($request->string('employment_type')->toString());

        return Inertia::render('DocumentTemplates/Create', [
            'type' => ($type !== null && in_array($type, DocumentTypeEnum::templatable(), true) ? $type : DocumentTypeEnum::ServiceAgreement)->value,
            'employment_type' => $type === DocumentTypeEnum::Employment ? $employmentType?->value : null,
            'isGlobal' => $request->boolean('global') && $user->can('createGlobal', DocumentTemplate::class),
            'tokens' => $this->catalogue->catalog(),
            'letterhead' => DocumentFrame::letterhead($tenant),
            'footer' => DocumentFrame::footer($tenant),
        ]);
    }

    #[Authorize('create', DocumentTemplate::class)]
    public function store(DocumentTemplateUpsertData $data): RedirectResponse
    {
        $template = $this->templates->create($data, $this->tenant());

        return to_route('document-templates.edit', $template)->with('success', __('app.document_template_created'));
    }

    #[Authorize('createGlobal', DocumentTemplate::class)]
    public function storeGlobal(DocumentTemplateUpsertData $data): RedirectResponse
    {
        $template = $this->templates->create($data, null);

        return to_route('document-templates.edit', $template)->with('success', __('app.document_template_created'));
    }

    #[Authorize('view', 'documentTemplate')]
    public function edit(DocumentTemplate $documentTemplate, Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('DocumentTemplates/Edit', [
            'template' => DocumentTemplateEditData::fromModel($documentTemplate, $this->tenant(), $user, $this->catalogue),
        ]);
    }

    #[Authorize('update', 'documentTemplate')]
    public function update(DocumentTemplateUpsertData $data, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->templates->update($documentTemplate, $data);

        return to_route('document-templates.edit', $documentTemplate)->with('success', __('app.document_template_updated'));
    }

    #[Authorize('delete', 'documentTemplate')]
    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->templates->delete($documentTemplate);

        return to_route('document-templates.index')->with('success', __('app.document_template_deleted'));
    }

    #[Authorize('customize', 'documentTemplate')]
    public function customize(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $override = $this->templates->customize($documentTemplate, $this->tenant());

        return to_route('document-templates.edit', $override)->with('success', __('app.document_template_customized'));
    }

    #[Authorize('reset', 'documentTemplate')]
    public function reset(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->templates->reset($documentTemplate);

        return to_route('document-templates.index')->with('success', __('app.document_template_reset'));
    }

    #[Authorize('acknowledge', 'documentTemplate')]
    public function acknowledge(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $this->templates->acknowledge($documentTemplate);

        return to_route('document-templates.edit', $documentTemplate)->with('success', __('app.document_template_acknowledged'));
    }

    #[Authorize('preview', DocumentTemplate::class)]
    public function preview(DocumentTemplatePreviewData $data): JsonResponse
    {
        return response()->json($this->templates->preview($data, $this->tenant())->toArray());
    }

    #[Authorize('preview', DocumentTemplate::class)]
    public function previewPdf(DocumentTemplatePreviewData $data): Response
    {
        return response($this->templates->previewPdf($data, $this->tenant()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(current_tenant_id());
    }
}
