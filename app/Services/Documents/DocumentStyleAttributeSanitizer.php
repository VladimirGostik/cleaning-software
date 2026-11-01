<?php

declare(strict_types=1);

namespace App\Services\Documents;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

final class DocumentStyleAttributeSanitizer implements AttributeSanitizerInterface
{
    private const array ALLOWED_PROPERTIES = [
        'text-align', 'text-indent', 'text-decoration', 'font-weight', 'font-style', 'font-size', 'font-family',
        'line-height', 'color', 'background-color', 'vertical-align', 'width', 'height', 'min-width',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-collapse', 'border-spacing',
        'border-color', 'border-style', 'border-width', 'white-space', 'table-layout',
    ];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $declarations = [];

        foreach (explode(';', $value) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $propertyValue = trim($parts[1]);

            if (! in_array($property, self::ALLOWED_PROPERTIES, true) || ! $this->isSafeValue($propertyValue)) {
                continue;
            }

            $declarations[] = $property.': '.$propertyValue;
        }

        return $declarations === [] ? null : implode('; ', $declarations).';';
    }

    private function isSafeValue(string $value): bool
    {
        if ($value === '' || preg_match('/^[#a-z0-9.,%()\s\-\'"]+$/i', $value) !== 1) {
            return false;
        }

        return preg_match('/url\s*\(|expression|javascript|import|var\s*\(|calc\s*\(/i', $value) !== 1;
    }
}
