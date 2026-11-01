<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class DocumentHtmlSanitizer
{
    private const array BLOCK_ELEMENTS = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tfoot', 'tr',
    ];

    private const array INLINE_ELEMENTS = ['strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'span', 'mark', 'br', 'hr'];

    public function sanitize(string $html): string
    {
        return $this->sanitizer()->sanitize($html);
    }

    private function sanitizer(): HtmlSanitizer
    {
        $maxLength = config('documents.max_body_length', 200000);
        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength((is_numeric($maxLength) ? (int) $maxLength : 200000) * 4)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->withAttributeSanitizer(new DocumentStyleAttributeSanitizer);

        foreach (self::BLOCK_ELEMENTS as $element) {
            $config = $config->allowElement($element, ['style']);
        }

        foreach (self::INLINE_ELEMENTS as $element) {
            $config = $config->allowElement($element, ['style']);
        }

        return new HtmlSanitizer(
            $config
                ->allowElement('a', ['href', 'style'])
                ->allowElement('td', ['style', 'colspan', 'rowspan'])
                ->allowElement('th', ['style', 'colspan', 'rowspan'])
                ->allowElement('colgroup', ['style'])
                ->allowElement('col', ['style', 'span']),
        );
    }
}
