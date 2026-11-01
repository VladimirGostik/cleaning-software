<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\DocumentTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_document_templates')]
#[Description('Pages through the document templates a company can use: its own, its customized copies and the global ones. Filter by search (name), type or scope. Use get_document_template for the text.')]
#[IsReadOnly]
final class ListDocumentTemplatesTool extends ListTool
{
    protected function filterKeys(): array
    {
        return ['search', 'type', 'scope'];
    }

    public function execute(User $user, DocumentTemplateService $templates): Response
    {
        Gate::forUser($user)->authorize('viewAny', DocumentTemplate::class);

        return $this->json($this->pageOf($templates->paginate(request())));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'search' => $schema->string()->description('Template name contains.'),
            'type' => $schema->string()->enum(array_map(static fn (DocumentTypeEnum $type): string => $type->value, DocumentTypeEnum::templatable()))->description('Document type.'),
            'scope' => $schema->string()->enum(['global', 'override', 'firm'])->description('global: shared by all companies; override: this company\'s copy of a global template; firm: this company\'s own.'),
            ...$this->pagingProperties($schema),
        ];
    }
}
