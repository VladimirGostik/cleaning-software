<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Expenses\EmployeeExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmployeeNameMatchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    /** @param  array<string, mixed>  $attributes */
    private function person(string $first, string $last, array $attributes = []): TenantMembership
    {
        return TenantMembership::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'first_name' => $first,
            'last_name' => $last,
            ...$attributes,
        ]);
    }

    private function match(string $name, ?Tenant $tenant = null): ?TenantMembership
    {
        return app(EmployeeExpenseService::class)->matchEmployee($name, ($tenant ?? $this->tenant)->id);
    }

    public function test_both_name_orders_and_diacritics_match(): void
    {
        $person = $this->person('Ľudmila', 'Žilinská');

        $this->assertSame($person->id, $this->match('Ľudmila Žilinská')?->id);
        $this->assertSame($person->id, $this->match('ZILINSKA LUDMILA')?->id);
        $this->assertSame($person->id, $this->match('  žilinská,   ľudmila ')?->id);
    }

    public function test_a_partial_or_different_name_does_not_match(): void
    {
        $this->person('Ľudmila', 'Žilinská');

        $this->assertNull($this->match('Žilinská'));
        $this->assertNull($this->match('Ľudmila Žilinská Nováková'));
        $this->assertNull($this->match('Eva Žilinská'));
        $this->assertNull($this->match('   '));
    }

    public function test_two_equal_names_are_ambiguous(): void
    {
        $this->person('Jana', 'Dvojitá');
        $this->person('Dvojitá', 'Jana');

        $this->assertNull($this->match('Jana Dvojitá'));
    }

    public function test_an_active_person_wins_over_an_inactive_namesake(): void
    {
        $this->person('Jana', 'Bývalá', ['is_active' => false]);
        $active = $this->person('Jana', 'Bývalá');

        $this->assertSame($active->id, $this->match('Bývalá Jana')?->id);
    }

    public function test_a_single_inactive_person_still_matches(): void
    {
        $gone = $this->person('Peter', 'Odišiel', ['is_active' => false]);

        $this->assertSame($gone->id, $this->match('Peter Odišiel')?->id);
    }

    public function test_display_name_is_the_fallback_and_other_firms_are_ignored(): void
    {
        $user = User::factory()->create(['name' => 'Marta Bezprofilová']);
        $noProfile = TenantMembership::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);
        $other = Tenant::factory()->create();
        TenantMembership::factory()->create(['tenant_id' => $other->id, 'user_id' => null, 'first_name' => 'Cudzia', 'last_name' => 'Firma']);

        $this->assertSame($noProfile->id, $this->match('Bezprofilová Marta')?->id);
        $this->assertNull($this->match('Firma Cudzia'));
    }
}
