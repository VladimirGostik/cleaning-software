<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Services\Documents\QuoteTextService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QuoteBuyerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config()->string('documents.disk'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Zimná údržba',
            'issue_date' => '2026-11-11',
            'valid_until' => '2026-12-11',
            'note' => null,
            'items' => [
                ['id' => null, 'description' => 'Posyp', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
            'customer_name' => 'Dom Rajčianska 32',
            'number' => null,
            'kind' => 'itemized',
            'currency' => 'EUR',
        ], $overrides);
    }

    private function printed(Quote $quote): string
    {
        $html = html_entity_decode(app(QuoteTextService::class)->printBody($quote));
        $text = preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', strip_tags(str_replace('<', ' <', $html)))) ?? '';
        $start = (int) mb_strpos($text, 'Objednávateľ');
        $end = mb_strpos($text, 'Dátum vystavenia');

        return mb_substr($text, $start, $end === false ? null : $end - $start);
    }

    /** @param  array<string, mixed>  $overrides */
    private function store(array $overrides = []): Quote
    {
        $this->post(route('quotes.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return Quote::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    }

    public function test_clientless_buyer_prints_structured_with_representative_ico_and_dic(): void
    {
        $this->actingAsTenantUser();

        $quote = $this->store([
            'customer_represented_by' => 'Správcovská s.r.o.',
            'customer_street' => 'Rajčianska 32',
            'customer_city' => 'Bratislava',
            'customer_postal_code' => '821 08',
            'customer_ico' => '35683073',
            'customer_dic' => '2020905865',
        ]);

        $this->assertSame('Dom Rajčianska 32', $quote->customer_name);
        $this->assertSame(
            'Objednávateľ Dom Rajčianska 32 V zastúpení: Správcovská s.r.o. Rajčianska 32 821 08 Bratislava IČO: 35683073 DIČ: 2020905865',
            trim($this->printed($quote)),
        );
    }

    public function test_clientless_name_only_prints_no_empty_lines_and_no_representation(): void
    {
        $this->actingAsTenantUser();

        $text = $this->printed($this->store());

        $this->assertSame('Objednávateľ Dom Rajčianska 32', trim($text));
    }

    public function test_linked_client_without_object_prints_client_details(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Klient a.s.', 'street' => 'Hlavná 1', 'city' => 'Zvolen', 'postal_code' => '960 01', 'ico' => '111', 'dic' => '222']);

        $text = $this->printed($this->store(['client_id' => $client->id, 'customer_name' => null]));

        $this->assertSame('Objednávateľ Klient a.s. Hlavná 1 960 01 Zvolen IČO: 111 DIČ: 222', trim($text));
    }

    public function test_object_with_billing_identity_prints_only_the_house_billing_fields_and_nothing_from_the_client(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Správca s.r.o.', 'ico' => '999', 'dic' => '888', 'street' => 'Klientska 9']);
        $object = CleaningObject::factory()->create([
            'client_id' => $client->id,
            'street' => 'Objektová 5',
            'billing_name' => 'Bytový dom Rajčianska',
            'billing_ico' => '777',
        ]);

        $text = $this->printed($this->store(['client_id' => $client->id, 'cleaning_object_id' => $object->id, 'customer_name' => null]));

        $this->assertSame('Objednávateľ Bytový dom Rajčianska IČO: 777', trim($text));
        $this->assertStringNotContainsString('999', $text);
        $this->assertStringNotContainsString('Správca', $text);
        $this->assertStringNotContainsString('V zastúpení', $text);
        $this->assertStringNotContainsString('Objektová', $text);
    }

    public function test_attach_client_clears_the_entered_party(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->store(['customer_represented_by' => 'Správca', 'customer_ico' => '1', 'customer_dic' => '2']);
        $client = Client::factory()->create();

        $quote = app(QuoteService::class)->attachClient($quote, $client->id, null);

        $this->assertNull($quote->customer_represented_by);
        $this->assertNull($quote->customer_ico);
        $this->assertNull($quote->customer_dic);
        $this->assertNull($quote->customer_name);
    }

    public function test_client_with_a_new_customer_field_is_refused(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();

        foreach (['customer_represented_by', 'customer_ico', 'customer_dic'] as $field) {
            $this->post(route('quotes.store'), $this->payload(['client_id' => $client->id, 'customer_name' => null, $field => 'x']))
                ->assertSessionHasErrors('client_id');
        }
    }
}
