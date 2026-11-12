<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Models\Client;
use App\Models\Tenant;
use App\Utils\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FiltersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string> */
    private function names(callable $apply): array
    {
        $query = Client::query()->withoutGlobalScopes();
        $apply($query);

        return array_map(
            static fn (mixed $name): string => is_string($name) ? $name : '',
            $query->orderBy('name')->pluck('name')->all(),
        );
    }

    private function seedNames(string ...$names): void
    {
        $tenant = Tenant::factory()->create();

        foreach ($names as $name) {
            Client::factory()->create(['name' => $name, 'tenant_id' => $tenant->id]);
        }
    }

    public function test_contains_ignores_accents_and_case(): void
    {
        $this->seedNames('Rajecká 16', 'Žilina', 'Other');

        foreach (['raje', 'rajecka', 'RAJECKÁ', 'rajecká 1'] as $needle) {
            $this->assertSame(['Rajecká 16'], $this->names(fn (Builder $q) => Filters::whereContains($q, 'name', $needle)), $needle);
        }

        $this->assertSame(['Žilina'], $this->names(fn (Builder $q) => Filters::whereContains($q, 'name', 'zilina')));
        $this->assertSame(['Žilina'], $this->names(fn (Builder $q) => Filters::whereContains($q, 'name', 'ŽIL')));
    }

    public function test_contains_treats_like_wildcards_literally(): void
    {
        $this->seedNames('100% clean', 'a_b', 'axb', '100 clean');

        $this->assertSame(['100% clean'], $this->names(fn (Builder $q) => Filters::whereContains($q, 'name', '100%')));
        $this->assertSame(['a_b'], $this->names(fn (Builder $q) => Filters::whereContains($q, 'name', 'a_b')));
    }

    public function test_contains_or_boolean_builds_an_or_group(): void
    {
        $this->seedNames('Alpha', 'Beta', 'Gamma');

        $names = $this->names(function (Builder $q): void {
            Filters::whereContains($q, 'name', 'alp');
            Filters::whereContains($q, 'name', 'GAM', 'or');
        });

        $this->assertSame(['Alpha', 'Gamma'], $names);
    }

    public function test_text_equals_ignores_accents_and_case_and_negates(): void
    {
        $this->seedNames('Rajecká 16', 'Rajecká 17');

        $this->assertSame(['Rajecká 16'], $this->names(fn (Builder $q) => Filters::whereTextEquals($q, 'name', 'rajecka 16')));
        $this->assertSame(['Rajecká 17'], $this->names(fn (Builder $q) => Filters::whereTextEquals($q, 'name', 'RAJECKA 16', negate: true)));
        $this->assertSame([], $this->names(fn (Builder $q) => Filters::whereTextEquals($q, 'name', 'rajecka')));
    }

    public function test_starts_with_matches_prefix_only(): void
    {
        $this->seedNames('image/png', 'x-image/png');

        $this->assertSame(['image/png'], $this->names(fn (Builder $q) => Filters::whereStartsWith($q, 'name', 'IMAGE/')));
    }
}
