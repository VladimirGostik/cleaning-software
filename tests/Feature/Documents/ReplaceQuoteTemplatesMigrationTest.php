<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Enums\DocumentTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\Tenant;
use App\Scopes\TenantOrGlobalScope;
use App\Services\Documents\DocumentTemplateCatalogue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

final class ReplaceQuoteTemplatesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function run_migration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_20_000200_replace_quote_templates_with_kegos_default.php');
        (new ReflectionMethod($migration, 'up'))->invoke($migration);
    }

    public function test_global_quote_body_becomes_the_skeleton_and_firm_quote_rows_are_soft_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        $global = DocumentTemplate::factory()->global()->quote()->create(['body' => '<p>old plain quote</p>']);
        $firm = DocumentTemplate::factory()->quote()->forTenant($tenant)->create();
        $override = DocumentTemplate::factory()->overrideOf($global, $tenant)->create();
        $agreement = DocumentTemplate::factory()->forTenant($tenant)->create();
        Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $context['firm_soft_deleted'] === 2 && $context['global_updated'] === 1);

        $this->run_migration();

        $this->assertSame(app(DocumentTemplateCatalogue::class)->skeleton(DocumentTypeEnum::Quote), $global->fresh()?->body);
        $this->assertSoftDeleted($firm);
        $this->assertSoftDeleted($override);
        $this->assertNotSoftDeleted($agreement);
        $this->assertNotNull(DocumentTemplate::query()->withoutGlobalScope(TenantOrGlobalScope::class)->withTrashed()->find($firm->id));
    }

    public function test_second_run_changes_nothing(): void
    {
        DocumentTemplate::factory()->global()->quote()->create(['body' => '<p>old</p>']);
        DocumentTemplate::factory()->quote()->forTenant(Tenant::factory()->create())->create();
        $this->run_migration();
        Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $context['firm_soft_deleted'] === 0 && $context['global_updated'] === 0);

        $this->run_migration();
    }

    public function test_documents_of_soft_deleted_firm_templates_are_repointed_to_the_global_one_and_stay_saveable(): void
    {
        $this->actingAsTenantUser();
        $tenantId = current_tenant_id();
        $global = DocumentTemplate::factory()->global()->quote()->create(['body' => '<p>{{customer.name}}</p>']);
        $firm = DocumentTemplate::factory()->quote()->create(['tenant_id' => $tenantId, 'body' => '<p>firm {{customer.name}}</p>']);
        $payload = [
            'client_id' => null, 'cleaning_object_id' => null, 'subject' => 'S', 'issue_date' => '2026-11-11', 'valid_until' => '2026-12-11', 'note' => null,
            'items' => [['id' => null, 'description' => 'A', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0]],
            'customer_name' => 'Zákazník', 'number' => null, 'kind' => 'itemized', 'currency' => 'EUR', 'document_template_id' => $firm->id,
        ];
        $this->post(route('quotes.store'), $payload)->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();
        $this->assertSame($firm->id, $quote->textDocument()?->document_template_id);

        $this->run_migration();

        $document = $quote->fresh()?->textDocument();
        $this->assertSame($global->id, $document?->document_template_id);
        $this->assertSame('<p>firm {{customer.name}}</p>', $document->body);
        $this->put(route('quotes.update', $quote), [...$payload, 'document_template_id' => $global->id, 'document_body' => '<p>firm {{customer.name}}</p>'])
            ->assertSessionHasNoErrors();
    }

    public function test_documents_are_unlinked_when_no_global_quote_template_exists(): void
    {
        $this->actingAsTenantUser();
        $firm = DocumentTemplate::factory()->quote()->create(['tenant_id' => current_tenant_id()]);
        $quote = Quote::factory()->create(['tenant_id' => current_tenant_id()]);
        $quote->document()->create(['tenant_id' => $quote->tenant_id, 'type' => 'quote', 'title' => 'x', 'body' => '<p>x</p>', 'document_template_id' => $firm->id]);

        $this->run_migration();

        $this->assertNull($quote->fresh()?->textDocument()?->document_template_id);
    }
}
