<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\Quotes\QuoteContractLinkData;
use App\Data\Quotes\QuoteConvertToContractData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteConvertToContractServiceTest extends TestCase
{
    use RefreshDatabase;

    private function quoteReadyForConversion(Tenant $tenant): Quote
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->accepted()->create([
            'tenant_id' => $tenant->id,
            'subject' => 'Pravidelné upratovanie',
            'number' => 'CP-2026-01',
            'note' => 'Poznámka k ponuke',
        ]);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Upratovanie kancelárie',
            'quantity' => 4,
            'unit_price' => 25,
        ]);

        return $quote;
    }

    public function test_converts_accepted_quote_to_draft_service_agreement_without_template(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->quoteReadyForConversion($tenant);

        $contract = app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);

        $this->assertSame(ContractCategoryEnum::ServiceAgreement, $contract->category);
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);
        $this->assertSame(ContractTermTypeEnum::Indefinite, $contract->term_type);
        $this->assertSame($quote->id, $contract->quote_id);
        $this->assertSame('CP-2026-01', $contract->number);
        $this->assertSame('Pravidelné upratovanie', $contract->title);
        $this->assertStringContainsString('Upratovanie kancelárie', (string) $contract->document?->body);
        $this->assertStringContainsString('100', (string) $contract->document?->body);
        $this->assertStringNotContainsString('{{block', (string) $contract->document?->body);
    }

    public function test_converts_with_template_resolving_quote_items_token(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->quoteReadyForConversion($tenant);
        $template = DocumentTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => DocumentTypeEnum::ServiceAgreement,
            'body' => '<p>Zoznam prác:</p><p>{{block.quote_items}}</p>',
        ]);

        $contract = app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData($template->id), $actor);

        $this->assertSame($template->id, $contract->document_template_id);
        $this->assertStringContainsString('Upratovanie kancelárie', (string) $contract->document?->body);
    }

    public function test_fails_for_document_kind_quote(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->document()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);
    }

    public function test_fails_when_quote_not_accepted(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);
    }

    public function test_fails_when_quote_has_no_client(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->withoutClient()->accepted()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);
    }

    public function test_fails_when_quote_has_no_object(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->forClient($client)->accepted()->create(['tenant_id' => $tenant->id, 'cleaning_object_id' => null]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);
    }

    public function test_reconversion_is_allowed_and_creates_second_contract(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->quoteReadyForConversion($tenant);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);
        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);

        $this->assertSame(2, Contract::where('quote_id', $quote->id)->count());
    }

    public function test_quote_detail_data_exposes_linked_contracts(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $quote = $this->quoteReadyForConversion($tenant);

        app(QuoteService::class)->convertToContract($quote, new QuoteConvertToContractData(null), $actor);

        $quote->refresh()->loadMissing('contracts');
        $link = $quote->contracts->sole();
        $data = QuoteContractLinkData::fromModel($link);

        $this->assertSame($link->id, $data->id);
        $this->assertSame(ContractStatusEnum::Draft, $data->status);
    }
}
