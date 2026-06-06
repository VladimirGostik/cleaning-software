<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ResetPasswordData extends Data
{
    public function __construct(
        #[Required]
        public string $token,
        #[Required, Email]
        public string $email,
        #[Required, Confirmed]
        public string $password,
        #[Required]
        public string $password_confirmation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'password' => ['confirmed', Password::defaults()],
        ];
    }
}
