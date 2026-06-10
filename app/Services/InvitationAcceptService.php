<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invitations\AcceptInvitationData;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Role;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final readonly class InvitationAcceptService
{
    public function __construct(
        private DatabaseManager $db,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    public function resolve(string $token): TenantInvitation
    {
        /** @var TenantInvitation $invitation */
        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)
            ->where('token', $token)
            ->firstOrFail();

        return $invitation;
    }

    /**
     * @param  bool  $skipPasswordCheck  Skip password verification — used when caller is already
     *                                   authenticated with the invited email (same-email auto-accept).
     */
    public function accept(TenantInvitation $invitation, AcceptInvitationData $data, bool $skipPasswordCheck = false): User
    {
        return $this->db->transaction(function () use ($invitation, $data, $skipPasswordCheck): User {
            abort_unless($invitation->isAcceptable(), 410);

            $existingUser = User::where('email', $invitation->email)->first();

            if ($existingUser !== null) {
                if (! $skipPasswordCheck && ! Hash::check($data->password, $existingUser->password)) {
                    throw ValidationException::withMessages([
                        'password' => [__('app.invalid_credentials')],
                    ]);
                }

                $user = $existingUser;
            } else {
                if ($data->name === null || $data->name === '') {
                    throw ValidationException::withMessages([
                        'name' => [__('validation.required', ['attribute' => 'name'])],
                    ]);
                }

                $user = User::create([
                    'name' => $data->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($data->password),
                    'is_active' => true,
                    'subscription_plan' => SubscriptionPlanEnum::Free,
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->permissionRegistrar->setPermissionsTeamId($invitation->tenant_id);

            $membership = TenantMembership::where('user_id', $user->id)
                ->where('tenant_id', $invitation->tenant_id)
                ->first();

            if ($membership !== null) {
                if (! $membership->is_active) {
                    $membership->forceFill(['is_active' => true])->save();
                }
            } else {
                TenantMembership::create([
                    'user_id' => $user->id,
                    'tenant_id' => $invitation->tenant_id,
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            }

            /** @var Role $role */
            $role = Role::where('name', $invitation->role_name)
                ->where('tenant_id', $invitation->tenant_id)
                ->firstOrFail();

            $user->assignRole($role);

            $invitation->markAccepted();

            return $user;
        });
    }
}
