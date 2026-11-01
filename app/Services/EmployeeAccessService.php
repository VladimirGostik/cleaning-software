<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Employees\EmployeeAccessData;
use App\Enums\SupportedLanguage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final readonly class EmployeeAccessService
{
    public function __construct(
        private DatabaseManager $db,
        private PermissionRegistrar $registrar,
        private RoleAssignmentGuard $guard,
        private RegistrationService $registration,
        private TaskService $tasks,
        private MobileSessionService $sessions,
    ) {}

    public function grant(TenantMembership $membership, EmployeeAccessData $data, User $actor): TenantMembership
    {
        $tenantId = $membership->tenant_id;
        $this->registrar->setPermissionsTeamId($tenantId);

        /** @var Role $role */
        $role = Role::inTenant($tenantId)->where('name', $data->role_name)->with('permissions')->firstOrFail();
        $this->guard->assertAssignable($actor, [$role]);
        $this->guard->assertPermissionsGrantable($actor, $data->permissions ?? []);

        return $this->db->transaction(function () use ($membership, $data, $actor, $role, $tenantId): TenantMembership {
            $user = User::query()->where('email', $data->email)->first();

            if ($user !== null && TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->whereKeyNot($membership->id)
                ->exists()) {
                throw ValidationException::withMessages(['email' => [__('app.employee_access_email_taken')]]);
            }

            if ($user === null) {
                /** @var User $user */
                $user = User::create([
                    'name' => trim(($membership->first_name ?? '').' '.($membership->last_name ?? '')) ?: $data->email,
                    'email' => $data->email,
                    'password' => null,
                    'locale' => SupportedLanguage::getDefault()->value,
                    'is_active' => true,
                ]);
            }

            $membership->forceFill(['user_id' => $user->id, 'contact_email' => null])->save();
            $membership->setRelation('user', $user);

            $user->syncRoles([$role]);
            $user->syncPermissions($data->permissions ?? []);

            if ($user->password === null) {
                $this->registration->createInvitation(Tenant::query()->findOrFail($tenantId), $actor, $data->email, $role->name);
            }

            activity()
                ->performedOn($membership)
                ->causedBy($actor)
                ->event('employee_access_granted')
                ->log('employee_access_granted');

            return $membership;
        });
    }

    public function revoke(TenantMembership $membership, User $actor): void
    {
        $tenantId = $membership->tenant_id;
        $this->registrar->setPermissionsTeamId($tenantId);

        $this->db->transaction(function () use ($membership, $actor, $tenantId): void {
            $membership->loadMissing('user');
            $user = $membership->user;

            if ($user === null) {
                return;
            }

            $user->syncRoles([]);
            $user->syncPermissions([]);

            $attributes = ['contact_email' => $user->email, 'user_id' => null];

            if (blank($membership->first_name) && blank($membership->last_name)) {
                [$first, $last] = array_pad(explode(' ', trim($user->name), 2), 2, null);
                $attributes += ['first_name' => $first, 'last_name' => $last];
            }

            $membership->forceFill($attributes)->save();
            $membership->setRelation('user', null);

            $this->registration->revokePendingInvitations($tenantId, $user->email);
            $this->tasks->unassignForMembership($membership);
            $this->sessions->revokeAllIfNoActiveMembership($user);

            activity()
                ->performedOn($membership)
                ->causedBy($actor)
                ->event('employee_access_revoked')
                ->log('employee_access_revoked');
        });
    }

    public function resendInvitation(TenantMembership $membership, User $actor): void
    {
        $tenantId = $membership->tenant_id;
        $this->registrar->setPermissionsTeamId($tenantId);

        $this->db->transaction(function () use ($membership, $actor, $tenantId): void {
            $membership->loadMissing('user');
            /** @var User $user */
            $user = $membership->user;
            /** @var Role $currentRole */
            $currentRole = $user->roles()->firstOrFail();
            $roleName = $currentRole->name;

            $this->registration->createInvitation(Tenant::query()->findOrFail($tenantId), $actor, $user->email, $roleName);

            activity()
                ->performedOn($membership)
                ->causedBy($actor)
                ->event('employee_access_invitation_resent')
                ->log('employee_access_invitation_resent');
        });
    }
}
