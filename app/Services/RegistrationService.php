<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tenants\AddTenantData;
use App\Data\Tenants\CompanyData;
use App\Data\Tenants\InviteData;
use App\Enums\InvitationStatusEnum;
use App\Enums\TenantColorEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
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

    public function createOwner(string $name, string $email, string $password, CompanyData $company): User
    {
        return $this->db->transaction(function () use ($name, $email, $password, $company): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            // email_verified_at is intentionally not in #[Fillable] (not mass-assignable from
            // request input). This app has no email-verification flow — auto-verify on create.
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->bootstrapTenant(
                user: $user,
                name: $company->name,
                ico: $company->ico,
                dic: $company->dic,
                vat_number: $company->vat_number,
                is_vat_payer: $company->is_vat_payer,
                address_line: $company->address_line,
                city: $company->city,
                postal_code: $company->postal_code,
                country: $company->country,
                color: null,
            );

            return $user;
        });
    }

    public function addTenant(User $user, AddTenantData $data): Tenant
    {
        return $this->db->transaction(function () use ($user, $data): Tenant {
            $copyColor = null;

            if ($data->copy_settings) {
                $activeTenantId = session('active_tenant_id');

                if ($activeTenantId) {
                    /** @var Tenant|null $activeTenant */
                    $activeTenant = Tenant::query()->find($activeTenantId);
                    /** @var TenantInterface|null $tenantInterface */
                    $tenantInterface = $activeTenant?->interface()->first();
                    $copyColor = $tenantInterface?->color;
                }
            }

            $tenant = $this->bootstrapTenant(
                user: $user,
                name: $data->name,
                ico: $data->ico,
                dic: null,
                vat_number: null,
                is_vat_payer: false,
                address_line: '',
                city: '',
                postal_code: '',
                country: 'SK',
                color: $data->copy_settings ? $copyColor : $data->color,
            );

            if ($data->leader_email !== null) {
                $this->createInvitations($tenant, $user, [
                    new InviteData(email: $data->leader_email, role_name: 'Vedúca'),
                ]);
            }

            return $tenant;
        });
    }

    private function bootstrapTenant(
        User $user,
        string $name,
        string $ico,
        ?string $dic,
        ?string $vat_number,
        bool $is_vat_payer,
        string $address_line,
        string $city,
        string $postal_code,
        string $country,
        ?TenantColorEnum $color,
    ): Tenant {
        $tenant = Tenant::create([
            'owner_id' => $user->id,
            'name' => $name,
            'ico' => $ico,
            'dic' => $dic,
            'vat_number' => $vat_number,
            'is_vat_payer' => $is_vat_payer,
            'address_line' => $address_line,
            'city' => $city,
            'postal_code' => $postal_code,
            'country' => $country,
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

        /** @var Role $ownerRole */
        $ownerRole = Role::where('name', RoleTemplatesSeeder::ADMIN_ROLE)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $user->assignRole($ownerRole);

        return $tenant;
    }

    /**
     * @param  array<int, InviteData>  $invites
     */
    private function createInvitations(Tenant $tenant, User $inviter, array $invites): void
    {
        foreach ($invites as $invite) {
            $invitation = TenantInvitation::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'email' => $invite->email,
                ],
                [
                    'invited_by_user_id' => $inviter->id,
                    'role_name' => $invite->role_name,
                    'token' => Str::random(64),
                    'status' => InvitationStatusEnum::Pending->value,
                    'expires_at' => now()->addDays(7),
                ],
            );

            Notification::route('mail', $invite->email)
                ->notify(new InvitationCreated($invitation->token, $tenant->name, $invite->role_name));
        }
    }
}
