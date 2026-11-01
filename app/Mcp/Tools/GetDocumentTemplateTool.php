<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\DocumentTemplates\DocumentTemplateEditData;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentTemplateCatalogue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_document_template')]
#[Description('One document template with its HTML body and the {{tokens}} allowed in it.')]
#[IsReadOnly]
final class GetDocumentTemplateTool extends TenantTool
{
    protected function rules(): array
    {
        return ['template_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, string $companyId, DocumentTemplateCatalogue $catalogue): Response
    {
        $template = DocumentTemplate::query()->findOrFail($this->id($request, 'template_id'));

        Gate::forUser($user)->authorize('view', $template);

        return $this->json(DocumentTemplateEditData::fromModel($template, Tenant::query()->findOrFail($companyId), $user, $catalogue));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'template_id' => $schema->string()->format('uuid')->description('Template id from list_document_templates.')->required(),
        ];
    }
}
