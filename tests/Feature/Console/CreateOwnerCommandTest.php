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
            '--name' => 'Ján Novák',
            '--email' => 'owner3@example.com',
            '--password' => 'SecurePassword123',
            '--company' => '',
            '--ico' => '12345678',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
