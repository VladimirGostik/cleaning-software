<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Data\Contracts\ContractGenerateData;
use App\Enums\DocumentTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\DocumentContextBuilder;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\PlaceholderResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DocumentContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function terms(string $type, string $id, string $category = 'service_agreement'): ContractGenerateData
    {
        return ContractGenerateData::from([
            'category' => $category,
            'term_type' => 'fixed',
            'contractable_type' => $type,
            'contractable_id' => $id,
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'title' => 'Titul zmluvy',
            'show_header' => true,
            'show_footer' => true,
        ]);
    }

    public function test_employee_name_falls_back_to_user_name_when_profile_blank(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'first_name' => null, 'last_name' => null]);

        $ctx = app(DocumentContextBuilder::class)->forContract($membership, $tenant, $this->terms('tenant_membership', $membership->id, 'employment'));

        $this->assertSame($membership->user?->name, $ctx->values['employee.name']);
    }

    public function test_employee_name_prefers_profile_name(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Jana', 'last_name' => 'Nováková']);

        $ctx = app(DocumentContextBuilder::class)->forContract($membership, $tenant, $this->terms('tenant_membership', $membership->id, 'employment'));

        $this->assertSame('Jana Nováková', $ctx->values['employee.name']);
    }

    public function test_quote_blocks_and_values_are_built_from_the_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->create(['tenant_id' => $tenant->id, 'total' => '150.00']);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'description' => 'Upratovanie kancelárie',
            'quantity' => 2, 'unit' => 'hod', 'unit_price' => 30, 'line_total' => 60,
        ]);

        $ctx = app(DocumentContextBuilder::class)->forContract($object, $tenant, $this->terms('cleaning_object', $object->id), $quote);

        $this->assertStringContainsString('Upratovanie kancelárie', $ctx->blocks['block.quote_items']);
        $this->assertStringContainsString('150', (string) $ctx->values['quote.total']);
        $this->assertTrue($ctx->flags['if.has_object']);
    }

    public function test_catalogue_labels_are_translated_and_limited_by_type(): void
    {
        $catalogue = app(DocumentTemplateCatalogue::class);

        $keys = $catalogue->keysForType(DocumentTypeEnum::Employment);
        $this->assertContains('employee.national_id', $keys);
        $this->assertNotContains('client.name', $keys);
        $this->assertNotContains('quote.total', $keys);
        $this->assertSame(__('app.document_token_tenant_name'), $catalogue->labelOf('tenant.name'));
        $this->assertContains('block.quote_items', $catalogue->keysForType(DocumentTypeEnum::Quote));
        $this->assertNotContains('block.parties', $catalogue->keysForType(DocumentTypeEnum::Quote));
    }

    public function test_plain_text_resolver_is_still_idempotent_for_invoice_mail(): void
    {
        $service = app(PlaceholderResolverService::class);
        $once = $service->resolve('{{tenant.name}} {{unknown.token}} {{tenant.ico}}', ['tenant.name' => 'CleanCo', 'tenant.ico' => null]);

        $this->assertSame('CleanCo {{unknown.token}} ', $once);
        $this->assertSame($once, $service->resolve($once, ['tenant.name' => 'CleanCo']));
    }
}
