<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Data\Documents\DocumentLetterheadData;
use App\Data\DocumentTemplates\DocumentTokenData;
use App\Models\Quote;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\QuoteTextService;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteTextDocumentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $document_template_id,
        public readonly ?string $document_template_name,
        public readonly string $body,
        public readonly bool $show_header,
        public readonly bool $show_footer,
        public readonly bool $is_frozen,
        /** @var string[] */
        public readonly array $missing_tokens,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $tokens,
        public readonly DocumentLetterheadData $letterhead,
        /** @var string[] */
        public readonly array $footer,
    ) {}

    /** $raw = body with tokens for the editor; otherwise the body as it prints right now. */
    public static function fromQuote(Quote $quote, QuoteTextService $text, bool $raw = false): ?self
    {
        $quote->loadMissing('document.template');
        $document = $quote->document;

        if ($document === null || $document->body === null) {
            return null;
        }

        $tenant = $text->tenant($quote);
        $frozen = $text->isFrozen($quote);

        return new self(
            id: $document->id,
            document_template_id: $document->document_template_id,
            document_template_name: $document->template?->name,
            body: $raw && ! $frozen ? $document->body : (string) $text->resolvedBody($quote),
            show_header: $document->show_header,
            show_footer: $document->show_footer,
            is_frozen: $frozen,
            missing_tokens: $text->missingTokens($quote),
            tokens: $text->tokens(),
            letterhead: DocumentFrame::letterhead($tenant),
            footer: DocumentFrame::footer($tenant),
        );
    }
}
