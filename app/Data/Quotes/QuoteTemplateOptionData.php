<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\DocumentTemplateScopeEnum;
use App\Models\DocumentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteTemplateOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly DocumentTemplateScopeEnum $scope,
        public readonly string $body,
    ) {}

    public static function fromModel(DocumentTemplate $template): self
    {
        return new self($template->id, $template->name, $template->templateScope(), $template->body);
    }
}
