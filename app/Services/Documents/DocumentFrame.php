<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\DocumentLetterheadData;
use App\Enums\DocumentTypeEnum;
use App\Models\Tenant;

final class DocumentFrame
{
    public static function letterhead(Tenant $tenant): DocumentLetterheadData
    {
        return DocumentLetterheadData::fromTenant($tenant);
    }

    /** @return list<string> */
    public static function footer(Tenant $tenant, ?DocumentTypeEnum $type = null): array
    {
        if ($type === DocumentTypeEnum::Quote) {
            return self::quoteFooter($tenant);
        }

        return array_values(array_filter([
            $tenant->name,
            $tenant->contact_email,
            format_phone($tenant->contact_phone),
            $tenant->registration_info,
        ], static fn (?string $line): bool => $line !== null && trim($line) !== ''));
    }

    /** @return list<string> left "name · address", right "IČO · DIČ" */
    public static function quoteFooter(Tenant $tenant): array
    {
        $locality = implode(' ', array_filter([$tenant->postal_code, $tenant->city]));

        return app(DocumentLocale::class)->run(fn (): array => [
            implode(' · ', array_filter([$tenant->name, implode(', ', array_filter([$tenant->address_line, $locality]))])),
            implode(' · ', array_filter([
                $tenant->ico ? __('app.quote_pdf_ico').' '.$tenant->ico : null,
                $tenant->dic ? __('app.quote_pdf_dic').' '.$tenant->dic : null,
            ])),
        ]);
    }
}
