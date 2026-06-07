<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Data\Tenants\CompanyData;
use App\Data\Tenants\InviteData;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RegisterData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Email, Max(255), Unique('users', 'email')]
        public string $email,
        #[Required, Confirmed]
        public string $password,
        #[Accepted]
        public bool $terms_accepted,
        public CompanyData $company,
        /** @var DataCollection<int, InviteData> */
        #[DataCollectionOf(InviteData::class)]
        public DataCollection $invites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'invites' => ['max:3'],
            'password' => ['confirmed', Password::defaults()],
            'company.vat_number' => ['required_if:company.is_vat_payer,true'],
        ];
    }
}
