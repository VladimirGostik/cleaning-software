<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Data\Contracts\ContractGenerateData;
use App\Enums\DocumentTokenKindEnum;
use App\Enums\DocumentTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\DocumentContextBuilder;
use App\Services\Documents\DocumentHtmlSanitizer;
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

    public function test_quote_tokens_split_by_billing_period_and_blocks_use_period_cells(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $this->bindTenant($tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'period_totals' => [
            ['billing_period' => 'one_off', 'subtotal' => '300.00', 'vat_amount' => '0.00', 'total' => '300.00'],
            ['billing_period' => 'monthly', 'subtotal' => '120.00', 'vat_amount' => '0.00', 'total' => '120.00'],
        ]]);
        QuoteItem::factory()->monthly()->create([
            'tenant_id' => $tenant->id, 'quote_id' => $quote->id, 'description' => 'Schody', 'note' => 'Zametanie',
            'quantity' => 2, 'unit' => 'upratovanie', 'unit_price' => 25, 'line_base' => 50, 'line_total' => 50,
        ]);

        $ctx = app(DocumentContextBuilder::class)->forQuote($quote, $tenant);

        $this->assertSame("300,00\u{00A0}EUR jednorazovo + 120,00\u{00A0}EUR mesačne", $ctx->values['quote.total']);
        $this->assertSame("300,00\u{00A0}EUR", $ctx->values['quote.total_one_off']);
        $this->assertSame("120,00\u{00A0}EUR", $ctx->values['quote.total_monthly']);
        $this->assertNull($ctx->values['quote.total_quarterly']);
        $this->assertNull($ctx->values['quote.total_yearly']);
        $items = $ctx->blocks['block.quote_items'];
        $this->assertStringContainsString('<strong>Schody</strong>', $items);
        $this->assertStringContainsString('Zametanie', $items);
        $this->assertStringContainsString('2× mesačne', $items);
        $this->assertStringContainsString("50,00\u{00A0}EUR / mes.", $items);
        $this->assertStringContainsString("120,00\u{00A0}EUR / mes.", $ctx->blocks['block.quote_totals']);
        $this->assertStringNotContainsString('(', $items);
        $sanitized = app(DocumentHtmlSanitizer::class)->sanitize($items);
        $this->assertStringContainsString('<strong>Schody</strong>', $sanitized);
        $this->assertStringContainsString('Zametanie', $sanitized);
    }

    public function test_period_tokens_are_optional_quote_tokens_with_labels(): void
    {
        $catalogue = app(DocumentTemplateCatalogue::class);

        foreach (['one_off', 'monthly', 'quarterly', 'yearly'] as $period) {
            $this->assertSame(DocumentTokenKindEnum::Optional, $catalogue->kindOf('quote.total_'.$period));
            $this->assertContains('quote.total_'.$period, $catalogue->keysForType(DocumentTypeEnum::Quote));
        }
        $this->assertSame(DocumentTokenKindEnum::Text, $catalogue->kindOf('quote.total'));
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
