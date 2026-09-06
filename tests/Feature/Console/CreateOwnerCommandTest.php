<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CreateOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_owner_user_tenant_and_admin_role(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
        ])->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $this->assertSame('Demo Cleaning s.r.o.', $tenant->name);
        $this->assertSame('12345678', $tenant->ico);

        $this->assertDatabaseHas('tenant_interfaces', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('tenant_memberships', ['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $this->assertTrue($user->hasRole(RoleTemplatesSeeder::ADMIN_ROLE));
        $this->assertSame(count(PermissionEnum::cases()), $user->getAllPermissions()->count());
    }

    public function test_fails_with_invalid_email(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'not-an-email',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'taken@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
        ])->assertFailed();
    }

    public function test_fails_with_short_password(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner2@example.com',
            '--password' => 'short',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fails_with_missing_company(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner3@example.com',
            '--password' => 'SecurePassword123',
            '--company' => '',
            '--ico' => '12345678',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    // -------------------------------------------------------------------------
    // supplier profile flags
    // -------------------------------------------------------------------------

    public function test_all_supplier_flags_persist_every_tenant_column(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner4@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
            '--address-line' => 'Hlavná 1',
            '--city' => 'Bratislava',
            '--postal-code' => '811 01',
            '--country' => 'SK',
            '--dic' => '2012345678',
            '--vat-number' => 'SK2012345678',
            '--vat-payer' => true,
            '--contact-email' => 'fakturacia@democleaning.sk',
            '--contact-phone' => '+421900000000',
            '--iban' => 'SK8975000000000123456789',
            '--swift' => 'TATRSKBX',
        ])->assertSuccessful();

        $tenant = Tenant::where('owner_id', User::where('email', 'owner4@example.com')->firstOrFail()->id)->firstOrFail();

        $this->assertSame('Hlavná 1', $tenant->address_line);
        $this->assertSame('Bratislava', $tenant->city);
        $this->assertSame('811 01', $tenant->postal_code);
        $this->assertSame('SK', $tenant->country);
        $this->assertSame('2012345678', $tenant->dic);
        $this->assertSame('SK2012345678', $tenant->vat_number);
        $this->assertTrue($tenant->is_vat_payer);
        $this->assertSame('fakturacia@democleaning.sk', $tenant->contact_email);
        $this->assertSame('+421900000000', $tenant->contact_phone);
        $this->assertSame('SK8975000000000123456789', $tenant->iban);
        $this->assertSame('TATRSKBX', $tenant->swift_bic);
        $this->assertSame([], $tenant->missingSupplierFields());
    }

    public function test_legacy_five_flags_only_leaves_optional_columns_null(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner5@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
        ])->assertSuccessful();

        $tenant = Tenant::where('owner_id', User::where('email', 'owner5@example.com')->firstOrFail()->id)->firstOrFail();

        $this->assertNull($tenant->address_line);
        $this->assertNull($tenant->city);
        $this->assertNull($tenant->postal_code);
        $this->assertSame('SK', $tenant->country);
        $this->assertFalse($tenant->is_vat_payer);
    }

    public function test_fails_with_invalid_iban(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner6@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
            '--iban' => 'invalid',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fails_with_invalid_contact_email(): void
    {
        $this->artisan('app:create-owner', [
            '--no-interaction' => true,
            '--name' => 'Ján Novák',
            '--email' => 'owner7@example.com',
            '--password' => 'SecurePassword123',
            '--company' => 'Demo Cleaning s.r.o.',
            '--ico' => '12345678',
            '--contact-email' => 'not-an-email',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
