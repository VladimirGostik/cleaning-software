<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateUserData;
use App\Data\UpdateUserData;
use App\Enums\SupportedLanguage;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final readonly class UserService
{
    public function __construct(
        private DatabaseManager $db,
        private PermissionRegistrar $registrar,
        private RoleAssignmentGuard $guard,
    ) {}

    public function create(CreateUserData $data, User $actor): User
    {
        return $this->db->transaction(function () use ($data, $actor): User {
            $tenantId = current_tenant_id();
            $this->registrar->setPermissionsTeamId($tenantId);

            $roles = Role::inTenant($tenantId)->whereIn('name', $data->roles)->with('permissions')->get();
            $this->guard->assertAssignable($actor, $roles);

            $user = User::query()->where('email', $data->email)->first();

            if ($user === null) {
                /** @var User $user */
                $user = User::create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => Hash::make((string) $data->password),
                    'is_active' => true,
                    'locale' => SupportedLanguage::getDefault()->value,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $membership = TenantMembership::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($membership !== null && $membership->is_active) {
                throw ValidationException::withMessages([
                    'email' => [__('app.already_member')],
                ]);
            }

            if ($membership !== null) {
                $membership->forceFill(['is_active' => $data->is_active])->save();
            } else {
                TenantMembership::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'is_active' => $data->is_active,
                    'joined_at' => now(),
                ]);
            }

            $user->syncRoles($roles);

            return $user->fresh(['roles']);
        });
    }

    public function update(User $user, UpdateUserData $data, User $actor): User
    {
        return $this->db->transaction(function () use ($user, $data, $actor): User {
            $tenantId = current_tenant_id();
            $this->registrar->setPermissionsTeamId($tenantId);

            $roles = Role::inTenant($tenantId)->whereIn('name', $data->roles)->with('permissions')->get();
            $this->guard->assertAssignable($actor, $roles);

            $user->update([
                'name' => $data->name,
                'email' => $data->email,
            ]);

            TenantMembership::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->update(['is_active' => $data->is_active]);

            $user->syncRoles($roles);

            return $user->fresh(['roles']);
        });
    }

    public function delete(User $user): void
    {
        $this->db->transaction(function () use ($user): void {
            $tenantId = current_tenant_id();
            $this->registrar->setPermissionsTeamId($tenantId);

            $user->syncRoles([]);
            $user->syncPermissions([]);

            TenantMembership::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->delete();
        });
    }
}
