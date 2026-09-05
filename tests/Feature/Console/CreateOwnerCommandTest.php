<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_creates_owner_user_tenant_and_role_assignment(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Jozef Majiteľ',
            '--email' => 'owner@example.sk',
            '--password' => 'password123',
            '--company' => 'Prvá Firma s.r.o.',
            '--ico' => '11223344',
        ])->assertExitCode(0);

        $user = User::where('email', 'owner@example.sk')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->is_active);

        $tenant = Tenant::where('owner_id', $user->id)->firstOrFail();
        $this->assertSame('Prvá Firma s.r.o.', $tenant->name);
        $this->assertSame('11223344', $tenant->ico);

        $this->assertDatabaseHas('tenant_interfaces', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('tenant_memberships', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $this->assertCount(6, $roles);
        $this->assertContains('Admin', $roles);

        $this->assertTrue($user->hasRole('Admin'));
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_duplicate_email_fails_validation_and_writes_nothing(): void
    {
        User::factory()->create(['email' => 'existing@example.sk']);

        $userCountBefore = User::count();
        $tenantCountBefore = Tenant::count();

        $this->artisan('app:create-owner', [
            '--name' => 'Duplicitný',
            '--email' => 'existing@example.sk',
            '--password' => 'password123',
            '--company' => 'Duplicitná Firma s.r.o.',
            '--ico' => '22334455',
        ])->assertExitCode(1);

        $this->assertSame($userCountBefore, User::count());
        $this->assertSame($tenantCountBefore, Tenant::count());
    }

    public function test_password_under_eight_characters_fails_validation(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Krátke Heslo',
            '--email' => 'shortpass@example.sk',
            '--password' => 'short',
            '--company' => 'Firma s.r.o.',
            '--ico' => '33445566',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'shortpass@example.sk']);
    }

    public function test_blank_ico_fails_validation(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Bez IČO',
            '--email' => 'noico@example.sk',
            '--password' => 'password123',
            '--company' => 'Firma s.r.o.',
            '--ico' => '',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'noico@example.sk']);
    }

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_running_twice_with_different_emails_creates_two_independent_owners(): void
    {
        $this->artisan('app:create-owner', [
            '--name' => 'Prvý Majiteľ',
            '--email' => 'first@example.sk',
            '--password' => 'password123',
            '--company' => 'Prvá Firma s.r.o.',
            '--ico' => '44556677',
        ])->assertExitCode(0);

        $this->artisan('app:create-owner', [
            '--name' => 'Druhý Majiteľ',
            '--email' => 'second@example.sk',
            '--password' => 'password123',
            '--company' => 'Druhá Firma s.r.o.',
            '--ico' => '55667788',
        ])->assertExitCode(0);

        $firstUser = User::where('email', 'first@example.sk')->firstOrFail();
        $secondUser = User::where('email', 'second@example.sk')->firstOrFail();

        $firstTenant = Tenant::where('owner_id', $firstUser->id)->firstOrFail();
        $secondTenant = Tenant::where('owner_id', $secondUser->id)->firstOrFail();

        $this->assertNotSame($firstTenant->id, $secondTenant->id);
        $this->assertSame(2, User::count());
        $this->assertSame(2, Tenant::count());
    }
}
