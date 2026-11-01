<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Services\Documents\ContractTextService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

final class ContractFreezeBlocksTest extends TestCase
{
    use RefreshDatabase;

    private const string LEGACY_BODY = '<p>{{block.parties}}</p><p>Zmluvné podmienky pravidelného upratovania.</p><p>{{block.signatures}}</p>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config()->string('documents.disk'));
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_31_000500_freeze_blocks_in_non_draft_contracts.php');

        return $migration;
    }

    private function runMigration(): void
    {
        $migration = $this->migration();
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
    }

    private function storedBody(Contract $contract): string
    {
        return Crypt::decryptString(is_string($body = DB::table('documents')->where('documentable_id', $contract->id)->value('body')) ? $body : '');
    }

    public function test_signing_expands_blocks_into_the_stored_text(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Pôvodný klient']);
        $contract = Contract::factory()->forClient($client)->draft()->withBody(self::LEGACY_BODY)->create(['tenant_id' => $tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        $stored = $this->storedBody($contract);
        $this->assertStringNotContainsString('{{block.', $stored);
        $this->assertStringContainsString('Pôvodný klient', $stored);

        $client->update(['name' => 'Premenovaný klient']);
        $body = app(ContractTextService::class)->displayBody($contract->fresh() ?? $contract);
        $this->assertStringContainsString('Pôvodný klient', (string) $body);
        $this->assertStringNotContainsString('Premenovaný klient', (string) $body);
    }

    public function test_migration_freezes_non_draft_contracts_only_and_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Pôvodný klient']);
        $active = Contract::factory()->forClient($client)->active()->withBody(self::LEGACY_BODY)->create(['tenant_id' => $tenant->id]);
        $draft = Contract::factory()->forClient($client)->draft()->withBody(self::LEGACY_BODY)->create(['tenant_id' => $tenant->id]);

        $this->runMigration();
        $this->runMigration();

        $stored = $this->storedBody($active);
        $this->assertStringNotContainsString('{{block.', $stored);
        $this->assertStringContainsString('Pôvodný klient', $stored);
        $this->assertStringContainsString('Zmluvné podmienky pravidelného upratovania.', $stored);
        $this->assertSame(self::LEGACY_BODY, $this->storedBody($draft));
        $this->assertTrue(app()->bound('current_tenant_id'));
        $this->assertSame($tenant->id, current_tenant_id());

        $client->update(['name' => 'Premenovaný klient']);
        $this->assertStringContainsString('Pôvodný klient', (string) app(ContractTextService::class)->displayBody($active->fresh() ?? $active));
    }

    public function test_missing_tokens_ignore_expandable_blocks_without_building_a_context(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->draft()
            ->withBody(self::LEGACY_BODY.'<p>{{client.dic}}</p>')->create(['tenant_id' => $tenant->id]);
        $contract = Contract::query()->with('document')->findOrFail($contract->id);

        DB::enableQueryLog();
        $missing = app(ContractTextService::class)->missingTokens($contract);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['client.dic'], $missing);
        $this->assertSame([], $queries);
    }
}
