<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\DocumentTemplates\DocumentTokenData;
use App\Data\Quotes\QuoteTextPreviewData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\DocumentTokenKindEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

final readonly class QuoteTextService
{
    public function __construct(
        private DocumentTemplateRenderer $renderer,
        private DocumentContextBuilder $contexts,
        private DocumentTemplateCatalogue $catalogue,
        private DocumentTemplateService $templates,
        private DocumentHtmlSanitizer $sanitizer,
    ) {}

    public function isFrozen(Quote $quote): bool
    {
        return $quote->status !== QuoteStatusEnum::Draft;
    }

    public function defaultTemplate(string $tenantId): ?DocumentTemplate
    {
        return $this->templates->defaultFor(DocumentTypeEnum::Quote, $tenantId);
    }

    public function defaultBody(string $tenantId): string
    {
        return $this->bodyOf($this->defaultTemplate($tenantId));
    }

    public function bodyOf(?DocumentTemplate $template): string
    {
        return $template->body ?? $this->catalogue->skeleton(DocumentTypeEnum::Quote);
    }

    /** Sanitized token text the payload carries (its own body, else its template's), validated; null = the payload sends no text. */
    public function explicitBody(QuoteUpsertData $data): ?string
    {
        $body = $data->document_body;

        if ($body === null && $data->document_template_id !== null) {
            $body = DocumentTemplate::query()->find($data->document_template_id)?->body;
        }

        if ($body === null) {
            return null;
        }

        $unknown = $this->renderer->violations(DocumentTypeEnum::Quote, $body);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'document_body' => [__('app.document_template_unknown_tokens', ['tokens' => implode(', ', $unknown)])],
            ]);
        }

        return $this->sanitizer->sanitize($body);
    }

    /** Token text a save of this payload would leave on the quote. */
    public function bodyFor(QuoteUpsertData $data, ?Quote $existing, string $tenantId): string
    {
        return $this->explicitBody($data) ?? ($existing !== null ? $this->tokenBody($existing) : $this->defaultBody($tenantId));
    }

    /** Text with tokens for the editor: the draft text, the text kept at send, or the firm default. */
    public function tokenBody(Quote $quote): string
    {
        return $this->tokenAndDefault($quote)['token'];
    }

    /** A sent quote prints the text frozen at send; everything else prints the token text rendered with the quote's data. */
    public function printBody(Quote $quote, ?string $tokenBody = null): string
    {
        $quote->loadMissing('document');
        $stored = $quote->textDocument()?->body;

        if ($tokenBody === null && $stored !== null && $this->isFrozen($quote)) {
            return $stored;
        }

        return $this->render($quote, $tokenBody ?? $this->tokenBody($quote));
    }

    /**
     * Everything a quote screen needs from one default-template lookup and one render.
     *
     * @return array{token: string, print: string, missing: list<string>, default: ?DocumentTemplate}
     */
    public function resolveFor(Quote $quote): array
    {
        ['token' => $token, 'default' => $default] = $this->tokenAndDefault($quote);
        $stored = $quote->textDocument()?->body;
        $print = $stored !== null && $this->isFrozen($quote) ? $stored : $this->render($quote, $token);

        return ['token' => $token, 'print' => $print, 'missing' => $this->renderer->unresolvedTokens($print), 'default' => $default];
    }

    /** @return list<string> */
    public function missingTokens(Quote $quote): array
    {
        return $this->renderer->unresolvedTokens($this->printBody($quote));
    }

    public function editorPreview(Quote $quote, string $tokenBody): QuoteTextPreviewData
    {
        $context = $this->contexts->forQuote($quote, $this->tenant($quote));
        $values = [];

        foreach ($this->tokens() as $token) {
            if (in_array($token->kind, [DocumentTokenKindEnum::Text, DocumentTokenKindEnum::Optional], true)) {
                $values[$token->key] = $context->values[$token->key] ?? null;
            }
        }

        return new QuoteTextPreviewData(
            values: $values,
            blocks: $context->blocks,
            missing_tokens: $this->renderer->unresolvedTokens($this->renderer->render($tokenBody, $context)),
        );
    }

    /** @return list<DocumentTokenData> */
    public function tokens(): array
    {
        return $this->catalogue->forType(DocumentTypeEnum::Quote);
    }

    /** @return array{token: string, default: ?DocumentTemplate} */
    private function tokenAndDefault(Quote $quote): array
    {
        $quote->loadMissing('document');
        $document = $quote->textDocument();
        $own = $document->draft_body ?? $document->body ?? null;

        if ($own !== null) {
            return ['token' => $own, 'default' => null];
        }

        $default = $this->defaultTemplate($quote->tenant_id);

        return ['token' => $this->bodyOf($default), 'default' => $default];
    }

    private function render(Quote $quote, string $tokenBody): string
    {
        return $this->renderer->render($tokenBody, $this->contexts->forQuote($quote, $this->tenant($quote)));
    }

    public function tenant(Quote $quote): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);
    }
}
