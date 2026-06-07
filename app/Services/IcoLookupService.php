<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenants\IcoLookupData;

final readonly class IcoLookupService
{
    /**
     * Mock IČO registry lookup. Returns prefill data for known IČOs.
     *
     * TODO: swap mock for ARES/FinStat HTTP client
     */
    public function lookup(string $ico): ?IcoLookupData
    {
        $registry = [
            '52119803' => new IcoLookupData(
                name: 'CleanPro Bratislava s.r.o.',
                dic: '2120947512',
                vat_number: 'SK2120947512',
                address_line: 'Obchodná 15',
                city: 'Bratislava',
                postal_code: '811 06',
            ),
            '12345678' => new IcoLookupData(
                name: 'Demo Cleaning s.r.o.',
                dic: '2000000001',
                vat_number: 'SK1234567890',
                address_line: 'Hlavná 1',
                city: 'Bratislava',
                postal_code: '811 01',
            ),
        ];

        return $registry[$ico] ?? null;
    }
}
