<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\DocumentLetterheadData;
use App\Models\Tenant;

final class DocumentFrame
{
    public static function letterhead(Tenant $tenant): DocumentLetterheadData
    {
        return DocumentLetterheadData::fromTenant($tenant);
    }

    /** @return list<string> */
    public static function footer(Tenant $tenant): array
    {
        return array_values(array_filter([
            $tenant->name,
            $tenant->contact_email,
            format_phone($tenant->contact_phone),
            $tenant->registration_info,
        ], static fn (?string $line): bool => $line !== null && trim($line) !== ''));
    }
}
