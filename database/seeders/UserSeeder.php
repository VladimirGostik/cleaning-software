<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Tenants\TenantSupplierProfileData;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->exists()) {
            return;
        }

        app(RegistrationService::class)->createOwner(
            name: 'Admin',
            email: 'admin@example.com',
            password: 'password',
            companyName: 'Demo Cleaning s.r.o.',
            ico: '12345678',
            supplier: new TenantSupplierProfileData(
                address_line: 'Hlavná 1',
                city: 'Bratislava',
                postal_code: '811 01',
                country: 'SK',
                dic: '2012345678',
                vat_number: 'SK2012345678',
                is_vat_payer: true,
                contact_email: 'fakturacia@democleaning.sk',
                contact_phone: '+421900000000',
                iban: 'SK8975000000000123456789',
                swift_bic: 'TATRSKBX',
            ),
        );
    }
}
