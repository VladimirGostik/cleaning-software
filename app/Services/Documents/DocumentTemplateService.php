<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\DocumentRenderData;
use App\Data\DocumentTemplates\DocumentTemplateListItemData;
use App\Data\DocumentTemplates\DocumentTemplatePreviewData;
use App\Data\DocumentTemplates\DocumentTemplateUpsertData;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class DocumentTemplateService
{
    public function __construct(
        private DatabaseManager $db,
        private DocumentHtmlSanitizer $sanitizer,
        private DocumentTemplateRenderer $renderer,
        private DocumentContextBuilder $contexts,
        private DocumentPdfRenderer $pdf,
        private DocumentLocale $locale,
    ) {}

    /** @return LengthAwarePaginator<int, DocumentTemplateListItemData> */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(DocumentTemplate::query())
            ->allowedFilters(
                AllowedFilter::search(['name']),
                AllowedFilter::dynamic('type'),
                AllowedFilter::dynamic('is_active')->boolean(),
                AllowedFilter::callback('scope', function (Builder $query, mixed $value): void {
                    match ($value) {
                        'global' => $query->whereNull('tenant_id'),
                        'override' => $query->whereNotNull('parent_id'),
                        'firm' => $query->whereNotNull('tenant_id')->whereNull('parent_id'),
                        default => null,
                    };
                }),
            )
            ->allowedSorts('name', 'type', 'updated_at')
            ->defaultSort('name')
            ->with('parent')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (DocumentTemplate $template) => DocumentTemplateListItemData::fromModel($template));
    }

    /** @return list<DocumentTemplate> */
    public function options(DocumentTypeEnum $type, ?EmploymentContractTypeEnum $employmentType = null): array
    {
        $tenantId = current_tenant_id();

        $templates = DocumentTemplate::query()
            ->active()
            ->where('type', $type->value)
            ->when($employmentType !== null, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('employment_type')->orWhere('employment_type', $employmentType?->value),
            ))
            ->whereNotIn('id', DocumentTemplate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('parent_id')
                ->whereNull('deleted_at')
                ->select('parent_id'))
            ->orderBy('name')
            ->get();

        return array_values($templates->all());
    }

    public function create(DocumentTemplateUpsertData $data, ?Tenant $tenant): DocumentTemplate
    {
        $body = $this->prepareBody($data->type, $data->body);

        return $this->db->transaction(fn (): DocumentTemplate => DocumentTemplate::create([
            'tenant_id' => $tenant?->id,
            'type' => $data->type,
            'employment_type' => $data->type === DocumentTypeEnum::Employment ? $data->employment_type : null,
            'name' => $data->name,
            'body' => $body,
            'show_header' => $data->show_header,
            'show_footer' => $data->show_footer,
            'is_active' => $data->is_active,
        ]));
    }

    public function update(DocumentTemplate $template, DocumentTemplateUpsertData $data): DocumentTemplate
    {
        $body = $this->prepareBody($template->parent_id === null ? $data->type : $template->type, $data->body);

        $attributes = [
            'name' => $data->name,
            'body' => $body,
            'show_header' => $data->show_header,
            'show_footer' => $data->show_footer,
            'is_active' => $data->is_active,
        ];

        if ($template->parent_id === null) {
            $attributes['type'] = $data->type;
            $attributes['employment_type'] = $data->type === DocumentTypeEnum::Employment ? $data->employment_type : null;
        }

        $this->db->transaction(fn () => $template->update($attributes));

        return $template;
    }

    public function companyTarget(DocumentTemplate $template, Tenant $tenant): DocumentTemplate
    {
        if ($template->tenant_id !== null) {
            return $template;
        }

        return DocumentTemplate::query()->where('tenant_id', $tenant->id)->where('parent_id', $template->id)->first() ?? $template;
    }

    /** Copy and edit succeed together, so a refused edit leaves no unchanged copy behind. */
    public function updateForCompany(DocumentTemplate $target, Tenant $tenant, DocumentTemplateUpsertData $data): DocumentTemplate
    {
        if ($target->tenant_id !== null) {
            return $this->update($target, $data);
        }

        return $this->db->transaction(fn (): DocumentTemplate => $this->update($this->customize($target, $tenant), $data));
    }

    public function delete(DocumentTemplate $template): void
    {
        $this->db->transaction(fn () => $template->delete());
    }

    public function customize(DocumentTemplate $global, Tenant $tenant): DocumentTemplate
    {
        try {
            return $this->db->transaction(fn (): DocumentTemplate => $this->createOverride($global, $tenant));
        } catch (UniqueConstraintViolationException) {
            return DocumentTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->where('parent_id', $global->id)
                ->firstOrFail();
        }
    }

    private function createOverride(DocumentTemplate $global, Tenant $tenant): DocumentTemplate
    {
        return DocumentTemplate::create([
            'tenant_id' => $tenant->id,
            'parent_id' => $global->id,
            'type' => $global->type,
            'employment_type' => $global->employment_type,
            'name' => $global->name,
            'body' => $global->body,
            'base_body' => $global->body,
            'base_hash' => hash('sha256', $global->body),
            'show_header' => $global->show_header,
            'show_footer' => $global->show_footer,
            'is_active' => $global->is_active,
        ]);
    }

    public function reset(DocumentTemplate $override): void
    {
        $this->db->transaction(function () use ($override): void {
            $override->delete();
        });
    }

    public function acknowledge(DocumentTemplate $override): DocumentTemplate
    {
        $parent = $override->parent;

        if ($parent === null) {
            return $override;
        }

        $this->db->transaction(fn () => $override->update([
            'base_body' => $parent->body,
            'base_hash' => hash('sha256', $parent->body),
        ]));

        return $override;
    }

    public function preview(DocumentTemplatePreviewData $data, Tenant $tenant): DocumentRenderData
    {
        $html = $this->renderSample($data);

        return new DocumentRenderData(
            html: $html,
            missing_tokens: array_values(array_diff(
                $this->renderer->unresolvedTokens($html),
                $this->renderer->violations($data->type, $html),
            )),
            unknown_tokens: $this->renderer->violations($data->type, $data->body),
            letterhead: $data->show_header ? DocumentFrame::letterhead($tenant) : null,
            footer: $data->show_footer ? DocumentFrame::footer($tenant) : [],
        );
    }

    public function previewPdf(DocumentTemplatePreviewData $data, Tenant $tenant): string
    {
        return $this->pdf->render(
            $this->locale->run(fn (): string => __('app.document_template_preview_title')),
            $this->renderSample($data),
            $tenant,
            $data->type,
            $data->show_header,
            $data->show_footer,
        );
    }

    private function renderSample(DocumentTemplatePreviewData $data): string
    {
        return $this->renderer->render(
            $this->sanitizer->sanitize($data->body),
            $this->contexts->sample($data->type, $data->employment_type),
        );
    }

    private function prepareBody(DocumentTypeEnum $type, string $body): string
    {
        $unknown = $this->renderer->violations($type, $body);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => [__('app.document_template_unknown_tokens', ['tokens' => implode(', ', $unknown)])],
            ]);
        }

        return $this->sanitizer->sanitize($body);
    }
}
