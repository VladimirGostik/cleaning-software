<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\DocumentTemplates\DocumentTemplateEditData;
use App\Data\DocumentTemplates\DocumentTemplateUpsertData;
use App\Enums\PermissionEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_document_template')]
#[Description('Changes the name, HTML body, frame or active flag of a document template. Omitted fields stay as they are. A global template is never changed here: this company gets its own copy with the change. Show the user the change first. Unknown {{tokens}} are refused and unsafe HTML is removed.')]
#[IsDestructive]
final class UpdateDocumentTemplateTool extends TenantTool
{
    protected function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid'],
        ];
    }

    public function execute(Request $request, User $user, string $companyId, DocumentTemplateService $templates, DocumentTemplateCatalogue $catalogue): Response
    {
        $tenant = Tenant::query()->findOrFail($companyId);
        $template = $templates->companyTarget(DocumentTemplate::query()->findOrFail($this->id($request, 'template_id')), $tenant);

        if ($template->tenant_id === null) {
            Gate::forUser($user)->authorize('customize', $template);

            if (! $user->can(PermissionEnum::EditDocumentTemplates->value)) {
                throw new AuthorizationException;
            }
        } else {
            Gate::forUser($user)->authorize('update', $template);
        }

        $data = DocumentTemplateUpsertData::validateAndCreate([
            'name' => $request->get('name', $template->name),
            'type' => $template->type->value,
            'body' => $request->get('body', $template->body),
            'show_header' => $request->get('show_header', $template->show_header),
            'show_footer' => $request->get('show_footer', $template->show_footer),
            'is_active' => $request->get('is_active', $template->is_active),
            'employment_type' => $template->employment_type?->value,
        ]);

        return $this->json(DocumentTemplateEditData::fromModel($templates->updateForCompany($template, $tenant, $data), $tenant, $user, $catalogue));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'template_id' => $schema->string()->format('uuid')->description('Template id from list_document_templates.')->required(),
            'name' => $schema->string()->max(255)->description('New name.'),
            'body' => $schema->string()->max(200000)->description('New HTML body. Use only the tokens listed by get_document_template.'),
            'show_header' => $schema->boolean()->description('Print the company letterhead.'),
            'show_footer' => $schema->boolean()->description('Print the company footer.'),
            'is_active' => $schema->boolean()->description('Offer the template for new documents.'),
        ];
    }
}
