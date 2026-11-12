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
        public readonly ?string $id,
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

    /** $raw = token text for the editor (a sent quote gives back the text kept at send); otherwise the text as it prints right now. */
    public static function fromQuote(Quote $quote, QuoteTextService $text, bool $raw = false): ?self
    {
        if ($quote->isDocument()) {
            return null;
        }

        $quote->loadMissing('document.template');
        $document = $quote->textDocument();
        $resolved = $text->resolveFor($quote);
        $template = $document !== null ? $document->template : $resolved['default'];

        $tenant = $text->tenant($quote);
        $editing = $raw && $quote->isEditable();
        $frozen = ! $editing && $document?->body !== null && $text->isFrozen($quote);

        return new self(
            id: $document?->id,
            document_template_id: $document !== null ? $document->document_template_id : $template?->id,
            document_template_name: $template?->name,
            body: $editing ? $resolved['token'] : $resolved['print'],
            show_header: $document->show_header ?? true,
            show_footer: $document->show_footer ?? true,
            is_frozen: $frozen,
            missing_tokens: $resolved['missing'],
            tokens: $text->tokens(),
            letterhead: DocumentFrame::letterhead($tenant),
            footer: DocumentFrame::quoteFooter($tenant),
        );
    }
}
