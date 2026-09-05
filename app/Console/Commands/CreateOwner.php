<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Tenants\CompanyData;
use App\Services\RegistrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-owner {--name=} {--email=} {--password=} {--company=} {--ico=}')]
#[Description('Create the first owner account and its company')]
final class CreateOwner extends Command
{
    public function handle(RegistrationService $service): int
    {
        $name = $this->option('name') ?? text('Owner name');
        $email = $this->option('email') ?? text('Owner email');
        $password = $this->option('password') ?? password('Owner password');
        $company = $this->option('company') ?? text('Company name');
        $ico = $this->option('ico') ?? text('IČO');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'company' => $company,
                'ico' => $ico,
            ],
            [
                'name' => ['required', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'min:8'],
                'company' => ['required', 'max:255'],
                'ico' => ['required'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $companyData = new CompanyData(
            name: $company,
            ico: $ico,
            dic: null,
            vat_number: null,
            is_vat_payer: false,
            address_line: '',
            city: '',
            postal_code: '',
            country: 'SK',
        );

        $user = $service->createOwner($name, $email, $password, $companyData);

        $this->info("Owner {$user->email} created successfully.");

        return self::SUCCESS;
    }
}
