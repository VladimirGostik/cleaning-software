<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentTokenKindEnum;
use App\Enums\DocumentTypeEnum;

final readonly class DocumentTemplateRenderer
{
    private const string DOCUMENT_PATTERN = '#<(p|li)(\s[^>]*)?>(.*?)</\1>|\{\{\s*([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)\s*\}\}#is';

    private const string BLOCK_ONLY_PATTERN = '/^\s*\{\{\s*(block\.[a-z0-9_]+)\s*\}\}\s*$/i';

    public function __construct(private DocumentTemplateCatalogue $catalogue) {}

    public function render(string $html, DocumentRenderContext $ctx): string
    {
        $placeholders = [];

        $rendered = preg_replace_callback(
            self::DOCUMENT_PATTERN,
            function (array $match) use ($ctx, &$placeholders): string {
                if (($match[4] ?? '') !== '') {
                    $removed = false;

                    return $this->replaceTokens($match[0], $ctx, $placeholders, $removed);
                }

                return $this->renderParagraph($match, $ctx, $placeholders);
            },
            $html,
        ) ?? $html;

        return strtr($rendered, $placeholders);
    }

    /** @return list<string> */
    public function unresolvedTokens(string $html): array
    {
        preg_match_all(DocumentTemplateCatalogue::TOKEN_PATTERN, $html, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /** @return list<string> */
    public function violations(DocumentTypeEnum $type, string $html): array
    {
        $allowed = $this->catalogue->keysForType($type);

        return array_values(array_diff($this->unresolvedTokens($html), $allowed));
    }

    public function highlightMissing(string $html, DocumentTypeEnum $type): string
    {
        return preg_replace_callback(
            DocumentTemplateCatalogue::TOKEN_PATTERN,
            fn (array $match): string => '<mark>('.e($this->catalogue->kindOf(strtolower($match[1])) !== null
                ? $this->catalogue->labelOf(strtolower($match[1]))
                : $match[1]).')</mark>',
            $html,
        ) ?? $html;
    }

    /**
     * @param  array<int, string>  $match
     * @param  array<string, string>  $placeholders
     */
    private function renderParagraph(array $match, DocumentRenderContext $ctx, array &$placeholders): string
    {
        [$tag, $attributes, $inner] = [$match[1], $match[2] ?? '', $match[3]];

        if (preg_match(self::BLOCK_ONLY_PATTERN, strip_tags($inner), $block) === 1 && isset($ctx->blocks[strtolower($block[1])])) {
            return $this->placeholder(strtolower($block[1]), $ctx->blocks[strtolower($block[1])], $placeholders);
        }

        $keep = true;
        $inner = $this->applyFlags($inner, $ctx, $keep);

        if (! $keep) {
            return '';
        }

        $removedOptional = false;
        $inner = $this->replaceTokens($inner, $ctx, $placeholders, $removedOptional);

        if ($removedOptional && $this->isEmpty($inner)) {
            return '';
        }

        return '<'.$tag.$attributes.'>'.$inner.'</'.$tag.'>';
    }

    private function applyFlags(string $inner, DocumentRenderContext $ctx, bool &$keep): string
    {
        $result = preg_replace_callback(
            DocumentTemplateCatalogue::TOKEN_PATTERN,
            function (array $match) use ($ctx, &$keep): string {
                $key = strtolower($match[1]);

                if ($this->catalogue->kindOf($key) !== DocumentTokenKindEnum::Flag || ! array_key_exists($key, $ctx->flags)) {
                    return $match[0];
                }

                if (! $ctx->flags[$key]) {
                    $keep = false;
                }

                return '';
            },
            $inner,
        );

        return $result ?? $inner;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function replaceTokens(string $text, DocumentRenderContext $ctx, array &$placeholders, bool &$removedOptional): string
    {
        $removedOptional = false;

        $result = preg_replace_callback(
            DocumentTemplateCatalogue::TOKEN_PATTERN,
            function (array $match) use ($ctx, &$placeholders, &$removedOptional): string {
                $key = strtolower($match[1]);
                $kind = $this->catalogue->kindOf($key);
                $token = '{{'.$key.'}}';

                if ($kind === null || $kind === DocumentTokenKindEnum::Flag) {
                    return $token;
                }

                if ($kind === DocumentTokenKindEnum::Block) {
                    return isset($ctx->blocks[$key]) ? $this->placeholder($key, $ctx->blocks[$key], $placeholders) : $token;
                }

                if (! array_key_exists($key, $ctx->values)) {
                    return $token;
                }

                $value = $ctx->values[$key];

                if ($value === null || trim($value) === '') {
                    if ($kind === DocumentTokenKindEnum::Optional) {
                        $removedOptional = true;

                        return '';
                    }

                    return $token;
                }

                return e(str_replace("\0", '', $value));
            },
            $text,
        );

        return $result ?? $text;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function placeholder(string $key, string $html, array &$placeholders): string
    {
        $marker = "\0BLOCK:".count($placeholders).':'.$key."\0";
        $placeholders[$marker] = $html;

        return $marker;
    }

    private function isEmpty(string $inner): bool
    {
        $text = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5);

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }
}
