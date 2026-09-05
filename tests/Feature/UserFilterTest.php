<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Utils\SymbolOperators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('app:demo')->assertSuccessful();
    }

    public function test_symbol_operators_parse_handles_not_equal(): void
    {
        [$operator, $value] = SymbolOperators::parse('!=:admi');

        $this->assertSame('!=', $operator);
        $this->assertSame('admi', $value);
    }

    public function test_not_equal_filter_on_text_emits_not_equal_sql(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $sql = $this->captureUserSql($admin, '/users?filter%5Bname%5D=%21%3D%3AAlice');

        $this->assertContainsSqlFragment($sql, '"name" != ?');
    }

    public function test_not_equal_filter_on_boolean_excludes_active_users(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        User::factory()->inactive()->create(['name' => 'Inactive Carol']);

        $sql = $this->captureUserSql($admin, '/users?filter%5Bis_active%5D=%21%3D%3A1');

        $this->assertContainsSqlFragment($sql, '"is_active" != ?');

        $names = $this->fetchUserNames($admin, '/users?filter%5Bis_active%5D=%21%3D%3A1&per_page=100');

        $this->assertContains('Inactive Carol', $names);
        $this->assertNotContains('Admin', $names);
    }

    public function test_equal_filter_on_boolean_returns_only_active_users(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        User::factory()->inactive()->create(['name' => 'Inactive Dave']);

        $names = $this->fetchUserNames($admin, '/users?filter%5Bis_active%5D=1&per_page=100');

        $this->assertContains('Admin', $names);
        $this->assertNotContains('Inactive Dave', $names);
    }

    public function test_not_equal_filter_on_role_excludes_users_with_that_role(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $sql = $this->captureUserSql($admin, '/users?filter%5Brole%5D=%21%3D%3Aadmin');

        $this->assertContainsSqlFragment($sql, 'not exists');

        $names = $this->fetchUserNames($admin, '/users?filter%5Brole%5D=%21%3D%3Aadmin&per_page=100');

        $this->assertNotContains('Admin', $names);
        $this->assertNotEmpty($names);
    }

    public function test_equal_filter_on_role_returns_only_users_with_that_role(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $names = $this->fetchUserNames($admin, '/users?filter%5Brole%5D=admin&per_page=100');

        $this->assertSame(['Admin'], $names);
    }

    public function test_date_filter_supports_gte_operator(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $sql = $this->captureUserSql($admin, '/users?filter%5Bcreated_at%5D=%3E%3D%3A2026-01-01');

        $this->assertContainsSqlFragment($sql, '"created_at" >= ?');
    }

    public function test_date_filter_supports_between_operator(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $sql = $this->captureUserSql(
            $admin,
            '/users?filter%5Bcreated_at%5D=between%3A2026-01-01%2C2026-12-31',
        );

        $this->assertContainsSqlFragment($sql, '"created_at" between ? and ?');
    }

    /**
     * @return array<int, string>
     */
    private function captureUserSql(User $user, string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get($url)->assertSuccessful();

        return array_map(
            fn (array $entry): string => (string) $entry['query'],
            DB::getQueryLog(),
        );
    }

    /**
     * @param  array<int, string>  $queries
     */
    private function assertContainsSqlFragment(array $queries, string $fragment): void
    {
        $matches = array_filter($queries, fn (string $sql): bool => str_contains($sql, $fragment));

        $this->assertNotEmpty(
            $matches,
            'Expected SQL fragment `'.$fragment.'` but queries were: '.implode(' | ', $queries),
        );
    }

    /**
     * @return array<int, string>
     */
    private function fetchUserNames(User $user, string $url): array
    {
        $names = [];

        $this->actingAs($user)
            ->get($url)
            ->assertSuccessful()
            ->assertInertia(function (AssertableInertia $page) use (&$names): void {
                $rows = $page->toArray()['props']['users']['data'] ?? [];

                $names = array_map(
                    static fn (array $row): string => (string) $row['name'],
                    $rows,
                );
            });

        return $names;
    }
}
