<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\DocumentTemplates\DocumentTokenData;
use App\Enums\DocumentTypeEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use App\Models\Tenant;

final readonly class QuoteTextService
{
    public function __construct(
        private DocumentTemplateRenderer $renderer,
        private DocumentContextBuilder $contexts,
        private DocumentTemplateCatalogue $catalogue,
    ) {}

    public function hasText(Quote $quote): bool
    {
        $quote->loadMissing('document');

        return $quote->document?->body !== null;
    }

    public function isFrozen(Quote $quote): bool
    {
        return $quote->status !== QuoteStatusEnum::Draft;
    }

    /** Draft text follows the quote data; a sent quote keeps the text frozen at send. */
    public function resolvedBody(Quote $quote): ?string
    {
        $quote->loadMissing('document');
        $body = $quote->document?->body;

        if ($body === null || $this->isFrozen($quote)) {
            return $body;
        }

        return $this->renderer->render($body, $this->contexts->forQuote($quote, $this->tenant($quote)));
    }

    /** @return list<string> */
    public function missingTokens(Quote $quote): array
    {
        $body = $this->resolvedBody($quote);

        return $body === null ? [] : $this->renderer->unresolvedTokens($body);
    }

    /** @return list<DocumentTokenData> */
    public function tokens(): array
    {
        return $this->catalogue->forType(DocumentTypeEnum::Quote);
    }

    public function tenant(Quote $quote): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);
    }
}
