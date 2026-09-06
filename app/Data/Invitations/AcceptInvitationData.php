<?php

declare(strict_types=1);

namespace App\Data\Invitations;

use App\Models\TenantInvitation;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AcceptInvitationData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $password,
        #[Nullable, Max(255)]
        public readonly ?string $name = null,
    ) {}

    /**
     * New accounts get the standard password-strength rule; an existing account's
     * password field is a login check, not a new password, so it stays plain required.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $token = request()->route('token');

        if (! is_string($token)) {
            return [];
        }

        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)
            ->where('token', $token)
            ->first();

        $isNewUser = $invitation !== null && ! User::where('email', $invitation->email)->exists();

        if (! $isNewUser) {
            return [];
        }

        return [
            'password' => ['required', 'max:255', Password::min(8)],
        ];
    }
}
