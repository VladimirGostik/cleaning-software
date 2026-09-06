<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Tenants\TenantSupplierProfileData;
use App\Services\RegistrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-owner {--name=} {--email=} {--password=} {--company=} {--ico=} {--address-line=} {--city=} {--postal-code=} {--country=SK} {--dic=} {--vat-number=} {--vat-payer} {--contact-email=} {--contact-phone=} {--iban=} {--swift=}')]
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
                'ico' => ['required', 'max:20'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();

        $isVatPayer = (bool) $this->option('vat-payer')
            || ($interactive && confirm('VAT payer?', default: false));

        $country = $this->option('country');
        if ($interactive) {
            $country = text('Country (ISO 2)', default: $country ?? 'SK');
        }

        $supplierInput = [
            'address_line' => $this->nullableAnswer($this->option('address-line') ?? ($interactive ? text('Street and number') : null)),
            'city' => $this->nullableAnswer($this->option('city') ?? ($interactive ? text('City') : null)),
            'postal_code' => $this->nullableAnswer($this->option('postal-code') ?? ($interactive ? text('Postal code') : null)),
            'country' => $country ?? 'SK',
            'dic' => $this->nullableAnswer($this->option('dic') ?? ($interactive ? text('DIČ') : null)),
            'vat_number' => $this->nullableAnswer($this->option('vat-number') ?? ($interactive ? text('IČ DPH') : null)),
            'is_vat_payer' => $isVatPayer,
            'contact_email' => $this->nullableAnswer($this->option('contact-email') ?? ($interactive ? text('Contact e-mail') : null)),
            'contact_phone' => $this->nullableAnswer($this->option('contact-phone') ?? ($interactive ? text('Contact phone') : null)),
            'iban' => $this->nullableAnswer($this->option('iban') ?? ($interactive ? text('IBAN') : null)),
            'swift_bic' => $this->nullableAnswer($this->option('swift') ?? ($interactive ? text('SWIFT/BIC') : null)),
        ];

        try {
            $supplier = TenantSupplierProfileData::validateAndCreate($supplierInput);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $service->createOwner($name, $email, $password, $company, $ico, $supplier);

        $this->info("Owner {$user->email} created successfully.");

        return self::SUCCESS;
    }

    private function nullableAnswer(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
