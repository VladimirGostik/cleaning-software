<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        );
    }
}
