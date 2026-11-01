<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Contracts\RendersContractPdf;
use App\Data\Contracts\ContractGenerateData;
use App\Enums\DocumentTypeEnum;
use App\Events\ContractSigned;
use App\Listeners\StoreSignedContractPdf;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentContextBuilder;
use App\Services\Pdf\ContractPdfService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

final class DocumentLocaleTest extends TestCase
{
    use RefreshDatabase;

    private const string LEGACY_BODY = '<p>{{block.parties}}</p><p>Zmluvné podmienky pravidelného upratovania.</p><p>{{block.signatures}}</p>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config()->string('documents.disk'));
    }

    private function assertSlovakBlocks(string $html): void
    {
        foreach (['Dodávateľ', 'Zmluvná strana', 'Podpis dodávateľa', 'Podpis zmluvnej strany'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        foreach (['Supplier', 'Party', 'signature'] as $english) {
            $this->assertStringNotContainsString($english, $html);
        }
    }

    private function storedBody(Contract $contract): string
    {
        return Crypt::decryptString(is_string($body = DB::table('documents')->where('documentable_id', $contract->id)->value('body')) ? $body : '');
    }

    private function legacyContract(Tenant $tenant, bool $active = false): Contract
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $factory = Contract::factory()->forClient($client);
        $factory = $active ? $factory->active() : $factory->draft();

        return $factory->withBody(self::LEGACY_BODY)->create(['tenant_id' => $tenant->id]);
    }

    public function test_context_blocks_are_slovak_under_an_english_app_locale_and_the_locale_is_restored(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $terms = ContractGenerateData::from([
            'category' => 'service_agreement',
            'term_type' => 'indefinite',
            'contractable_type' => 'client',
            'contractable_id' => $client->id,
            'valid_from' => '2026-01-01',
        ]);
        App::setLocale('en');

        $context = app(DocumentContextBuilder::class)->forContract($client, $tenant, $terms);

        $this->assertSlovakBlocks($context->blocks['block.parties'].$context->blocks['block.signatures']);
        $this->assertSame('en', App::getLocale());
    }

    public function test_sample_context_is_slovak_under_an_english_app_locale(): void
    {
        App::setLocale('en');

        $context = app(DocumentContextBuilder::class)->sample(DocumentTypeEnum::ServiceAgreement, null);

        $this->assertSlovakBlocks($context->blocks['block.parties'].$context->blocks['block.signatures']);
        $this->assertSame('en', App::getLocale());
    }

    public function test_signing_as_a_ukrainian_user_freezes_slovak_text(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenant);
        $user->forceFill(['locale' => 'uk'])->save();
        $contract = $this->legacyContract($tenant);

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        $stored = $this->storedBody($contract);
        $this->assertSlovakBlocks($stored);
        $this->assertStringNotContainsString('{{block.', $stored);
        $this->assertSame('uk', App::getLocale());
    }

    public function test_display_body_is_slovak_under_an_english_app_locale(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = $this->legacyContract($tenant);
        App::setLocale('en');

        $body = (string) app(ContractTextService::class)->displayBody($contract->fresh() ?? $contract);

        $this->assertSlovakBlocks($body);
        $this->assertSame('en', App::getLocale());
    }

    public function test_migration_freezes_slovak_text_under_an_english_app_locale(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = $this->legacyContract($tenant, true);
        App::setLocale('en');

        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_31_000500_freeze_blocks_in_non_draft_contracts.php');
        (new ReflectionMethod($migration, 'up'))->invoke($migration);

        $this->assertSlovakBlocks($this->storedBody($contract));
        $this->assertSame('en', App::getLocale());
    }

    public function test_queued_listener_renders_a_slovak_pdf_under_an_english_app_locale(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $contract = $this->legacyContract($tenant, true);
        $this->app->singleton(RendersContractPdf::class, ContractPdfService::class);
        $fake = Pdf::fake();
        App::setLocale('en');

        $this->app->make(StoreSignedContractPdf::class)->handle(new ContractSigned($tenant->id, $contract->id));

        $fake->assertSee(['Dodávateľ', 'Podpis zmluvnej strany']);
        $fake->assertDontSee(['Supplier', 'Party signature']);
        $this->assertSame('en', App::getLocale());
    }
}
