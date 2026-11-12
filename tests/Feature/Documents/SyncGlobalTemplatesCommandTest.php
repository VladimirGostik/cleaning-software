<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentTemplateStateEnum;
use App\Enums\DocumentTypeEnum;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Scopes\TenantOrGlobalScope;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateService;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SyncGlobalTemplatesCommandTest extends TestCase
{
    use RefreshDatabase;

    private const NAME = 'Dohoda o mlčanlivosti';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DocumentTemplateSeeder::class);
    }

    private function global(): DocumentTemplate
    {
        return DocumentTemplate::query()->withoutGlobalScope(TenantOrGlobalScope::class)
            ->whereNull('tenant_id')->where('name', self::NAME)->firstOrFail();
    }

    private function seededBody(): string
    {
        return $this->global()->body;
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->global()->update(['body' => '<p>old</p>']);

        $this->artisanCommand('documents:sync-global-templates', ['--dry-run' => true])
            ->expectsOutputToContain('would update: '.self::NAME)
            ->assertSuccessful();

        $this->assertSame('<p>old</p>', $this->global()->body);
    }

    public function test_a_real_run_updates_a_changed_global_row_and_a_second_run_is_idempotent(): void
    {
        $seeded = $this->seededBody();
        $this->global()->update(['body' => '<p>old</p>']);

        $this->artisanCommand('documents:sync-global-templates')
            ->expectsOutputToContain('updated: '.self::NAME)
            ->assertSuccessful();
        $this->assertSame($seeded, $this->global()->body);

        $this->artisanCommand('documents:sync-global-templates')
            ->expectsOutputToContain('Updated 0 global template(s).')
            ->assertSuccessful();
    }

    public function test_firm_copies_keep_their_body_and_become_base_changed_and_documents_are_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $override = app(DocumentTemplateService::class)->customize($this->global(), $tenant);
        $override->update(['body' => '<p>Moja verzia</p>']);
        $document = Document::factory()->forEmployee(TenantMembership::factory()->create(['tenant_id' => $tenant->id]))
            ->create(['tenant_id' => $tenant->id, 'type' => DocumentTypeEnum::Nda, 'body' => '<p>Zmluva 1</p>']);
        $this->global()->update(['body' => '<p>old</p>']);
        $override = $override->fresh();
        $this->assertNotNull($override);
        $override->forceFill(['base_hash' => hash('sha256', '<p>old</p>')])->save();

        $this->artisanCommand('documents:sync-global-templates')->assertSuccessful();

        $fresh = DocumentTemplate::query()->withoutGlobalScopes()->findOrFail($override->id);
        $this->assertSame('<p>Moja verzia</p>', $fresh->body);
        $this->assertSame(DocumentTemplateStateEnum::BaseChanged, $fresh->state());
        $this->assertSame('<p>Zmluva 1</p>', $document->fresh()?->body);
    }

    public function test_soft_deleted_global_rows_stay_untouched(): void
    {
        $row = $this->global();
        $row->update(['body' => '<p>old</p>']);
        $row->delete();

        $this->artisanCommand('documents:sync-global-templates')->assertSuccessful();

        $trashed = DocumentTemplate::query()->withoutGlobalScopes()->findOrFail($row->id);
        $this->assertTrue($trashed->trashed());
        $this->assertSame('<p>old</p>', $trashed->body);
    }

    public function test_production_requires_the_force_flag_and_warns_about_platform_admin_edits(): void
    {
        $this->global()->update(['body' => '<p>old</p>']);
        $this->app['env'] = 'production';

        $this->artisanCommand('documents:sync-global-templates')
            ->expectsOutputToContain('platform admins will be replaced')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();
        $this->assertSame('<p>old</p>', $this->global()->body);

        $this->artisanCommand('documents:sync-global-templates', ['--force' => true])->assertSuccessful();
        $this->assertNotSame('<p>old</p>', $this->global()->body);
    }

    public function test_the_global_quote_template_is_the_kegos_skeleton_and_the_sync_restores_it(): void
    {
        $quote = fn (): DocumentTemplate => DocumentTemplate::query()->withoutGlobalScope(TenantOrGlobalScope::class)
            ->whereNull('tenant_id')->where('type', DocumentTypeEnum::Quote->value)->firstOrFail();
        $skeleton = app(DocumentTemplateCatalogue::class)->skeleton(DocumentTypeEnum::Quote);

        $this->assertSame($skeleton, $quote()->body);

        $quote()->update(['body' => '<p>old plain quote</p>']);
        $this->artisanCommand('documents:sync-global-templates')->assertSuccessful();

        $this->assertSame($skeleton, $quote()->body);
    }
}
