<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenants\AddTenantData;
use App\Enums\InvitationStatusEnum;
use App\Enums\SupportedLanguage;
use App\Enums\TenantColorEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
use App\Scopes\TenantScope;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

final readonly class RegistrationService
{
    public function __construct(
        private DatabaseManager $db,
        private PermissionRegistrar $permissionRegistrar,
    ) {}

    public function createOwner(string $name, string $email, string $password, string $companyName, string $ico): User
    {
        return $this->db->transaction(function () use ($name, $email, $password, $companyName, $ico): User {
            /** @var User $user */
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'locale' => SupportedLanguage::getDefault()->value,
            ]);

            // email_verified_at is intentionally not in #[Fillable] (not mass-assignable from
            // request input). This app has no email-verification flow — auto-verify on create.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->bootstrapTenant($user, $companyName, $ico, color: null);

            return $user;
        });
    }

    public function addTenant(User $user, AddTenantData $data): Tenant
    {
        return $this->db->transaction(function () use ($user, $data): Tenant {
            $color = $data->color;

            if ($data->copy_settings) {
                $activeTenantId = session('active_tenant_id');
                $color = is_string($activeTenantId)
                    ? Tenant::query()->find($activeTenantId)?->interface?->color
                    : null;
            }

            $tenant = $this->bootstrapTenant($user, $data->name, $data->ico, $color);

            if ($data->leader_email !== null) {
                $this->createInvitation($tenant, $user, $data->leader_email, 'Vedúca');
            }

            return $tenant;
        });
    }

    private function bootstrapTenant(User $user, string $name, string $ico, ?TenantColorEnum $color): Tenant
    {
        $tenant = Tenant::create([
            'owner_id' => $user->id,
            'name' => $name,
            'ico' => $ico,
            'country' => 'SK',
            'is_active' => true,
        ]);

        TenantInterface::create([
            'tenant_id' => $tenant->id,
            'color' => $color?->value,
        ]);

        TenantMembership::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->permissionRegistrar->setPermissionsTeamId($tenant->id);
        RoleTemplatesSeeder::seedForTenant($tenant);

        /** @var Role $role */
        $role = Role::inTenant($tenant->id)->where('name', RoleTemplatesSeeder::ADMIN_ROLE)->firstOrFail();

        $user->assignRole($role);

        return $tenant;
    }

    private function createInvitation(Tenant $tenant, User $inviter, string $email, string $roleName): TenantInvitation
    {
        /** @var TenantInvitation $invitation */
        $invitation = TenantInvitation::withoutGlobalScope(TenantScope::class)->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $email,
                'status' => InvitationStatusEnum::Pending->value,
            ],
            [
                'invited_by_user_id' => $inviter->id,
                'role_name' => $roleName,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
            ],
        );

        Notification::route('mail', $email)
            ->notify(new InvitationCreated($invitation->token, $tenant->name, $roleName));

        return $invitation;
    }
}
