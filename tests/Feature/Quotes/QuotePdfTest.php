<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\QuoteKindEnum;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Documents\DocumentLocale;
use App\Services\Pdf\TenantLogoResolver;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

final class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_note_is_rendered_in_pdf_html(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Office cleaning',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [new QuoteItemData(
                id: null,
                description: 'Cleaning service',
                frequency: null,
                quantity: 2,
                unit: 'hod',
                unit_price: 30,
                discount_percent: 0,
                vat_rate: 23,
                note: 'Watch out for the fragile vase',
            )],
            'customer_name' => 'Acme s.r.o.',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ]), null, 'sess-1');

        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        $html = View::make('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])->render();

        $this->assertStringContainsString('Watch out for the fragile vase', $html);
    }

    public function test_item_without_note_does_not_render_empty_note_line(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Office cleaning',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [new QuoteItemData(
                id: null,
                description: 'Cleaning service',
                frequency: null,
                quantity: 2,
                unit: 'hod',
                unit_price: 30,
                discount_percent: 0,
                vat_rate: 23,
            )],
            'customer_name' => 'Acme s.r.o.',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ]), null, 'sess-1');

        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        $html = View::make('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])->render();

        $this->assertStringContainsString('Cleaning service', $html);
    }

    public function test_quote_renders_kegos_header_currency_format_and_tenant_footer(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Quote Footer s.r.o.', 'ico' => '11223344']);
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Office cleaning',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [new QuoteItemData(
                id: null,
                description: 'Cleaning service',
                frequency: null,
                quantity: 1,
                unit: 'hod',
                unit_price: 1250,
                discount_percent: 0,
                vat_rate: 23,
            )],
            'customer_name' => 'Acme s.r.o.',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ]), null, 'sess-1');
        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        $html = View::make('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant])->render();
        $footer = View::make('pdf.quotes.footer', ['tenant' => $tenant])->render();

        $this->assertStringContainsString('aria-label="Kegos Clean"', $html);
        $this->assertStringContainsString('class="q-rule"', $html);
        $this->assertStringContainsString("1\u{00A0}250,00\u{00A0}EUR", $html);
        $this->assertStringContainsString('Quote Footer s.r.o.', $footer);
        $this->assertStringContainsString('11223344', $footer);
        $this->assertStringNotContainsString('totalPages', $footer);
    }

    /** @param list<QuoteItemData> $items */
    private function makeQuote(Tenant $tenant, array $items, ?string $includedText = null, ?string $clientId = null): Quote
    {
        $this->bindTenant($tenant);

        $quote = app(QuoteService::class)->create(QuoteUpsertData::from([
            'client_id' => $clientId,
            'cleaning_object_id' => null,
            'subject' => 'Office cleaning',
            'issue_date' => '2026-11-03',
            'valid_until' => '2026-12-03',
            'note' => null,
            'included_text' => $includedText,
            'items' => $items,
            'customer_name' => $clientId === null ? 'Acme s.r.o.' : null,
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ]), null, 'sess-1');

        return $quote->load(['items', 'client', 'cleaningObject']);
    }

    private function item(string $name, QuoteBillingPeriodEnum $period, float $quantity, float $price, ?string $unit = null, float $discount = 0, float $vat = 0): QuoteItemData
    {
        return new QuoteItemData(
            id: null,
            description: $name,
            frequency: null,
            quantity: $quantity,
            unit: $unit,
            unit_price: $price,
            discount_percent: $discount,
            vat_rate: $vat,
            billing_period: $period,
        );
    }

    private function render(Quote $quote, Tenant $tenant, ?string $logo = null): string
    {
        return app(DocumentLocale::class)->run(fn (): string => View::make('pdf.quotes.default', ['quote' => $quote, 'tenant' => $tenant, 'logoDataUri' => $logo])->render());
    }

    private function text(string $html): string
    {
        return preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', strip_tags(str_replace('<', ' <', $html)))) ?? '';
    }

    /** @return list<QuoteItemData> */
    private function mixedItems(): array
    {
        return [
            $this->item('Window cleaning', QuoteBillingPeriodEnum::OneOff, 1, 300),
            $this->item('Stairs', QuoteBillingPeriodEnum::Monthly, 1, 70),
            $this->item('Regular', QuoteBillingPeriodEnum::Monthly, 2, 25, 'upratovanie'),
        ];
    }

    public function test_mixed_periods_render_cells_and_split_totals_without_combined_total(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $quote = $this->makeQuote($tenant, $this->mixedItems());

        $text = $this->text($this->render($quote, $tenant));

        $this->assertStringContainsString('1× 300,00 EUR 300,00 EUR', $text);
        $this->assertStringContainsString('mesačne 70,00 EUR / mesiac 70,00 EUR / mes.', $text);
        $this->assertStringContainsString('2× mesačne 25,00 EUR / upratovanie 50,00 EUR / mes.', $text);
        $this->assertStringContainsString('Jednorazovo 300,00 EUR', $text);
        $this->assertStringContainsString('Mesačne 120,00 EUR / mes.', $text);
        $this->assertStringNotContainsString('420,00', $text);
        $this->assertStringContainsString('Poskytovateľ nie je platiteľom DPH.', $text);
        $this->assertStringContainsString('Popis služby Množstvo Cena za jednotku Celková cena', $text);
    }

    public function test_vat_payer_with_four_periods_prints_each_group_and_no_vat_recap(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => true]);
        $quote = $this->makeQuote($tenant, [
            $this->item('A', QuoteBillingPeriodEnum::OneOff, 1, 100, null, 0, 20),
            $this->item('B', QuoteBillingPeriodEnum::Monthly, 1, 50, null, 0, 20),
            $this->item('C', QuoteBillingPeriodEnum::Quarterly, 1, 80, null, 0, 20),
            $this->item('D', QuoteBillingPeriodEnum::Yearly, 4, 200, null, 0, 20),
        ]);

        $text = $this->text($this->render($quote, $tenant));

        $this->assertStringContainsString('Jednorazovo s DPH 120,00 EUR', $text);
        $this->assertStringContainsString('Mesačne s DPH 60,00 EUR / mes.', $text);
        $this->assertStringContainsString('Štvrťročne s DPH 96,00 EUR / štvrťrok', $text);
        $this->assertStringContainsString('Ročne s DPH 960,00 EUR / rok', $text);
        $this->assertStringContainsString('4× ročne', $text);
        $this->assertStringContainsString('bez DPH 100,00 EUR', $text);
        $this->assertStringNotContainsString('nie je platiteľom', $text);
    }

    public function test_discount_is_inline_and_frequency_status_are_not_printed(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = $this->makeQuote($tenant, [$this->item('A', QuoteBillingPeriodEnum::OneOff, 1, 100, null, 10)]);

        $text = $this->text($this->render($quote, $tenant));

        $this->assertStringContainsString('100,00 EUR, zľava 10 %', $text);
        $this->assertStringNotContainsString($quote->status->label(), $text);
    }

    public function test_included_section_only_when_text_has_lines(): void
    {
        $tenant = Tenant::factory()->create();
        $with = $this->makeQuote($tenant, $this->mixedItems(), "  Labour \n\n Material  \n");
        $without = $this->makeQuote($tenant, $this->mixedItems(), null);

        $htmlWith = $this->render($with, $tenant);

        $this->assertStringContainsString('<li>Labour</li>', $htmlWith);
        $this->assertStringContainsString('<li>Material</li>', $htmlWith);
        $this->assertStringNotContainsString('<ul class="q-inc"', $this->render($without, $tenant));
    }

    public function test_closing_line_uses_signing_place_then_city_then_bare_date(): void
    {
        $tenant = Tenant::factory()->create(['signing_place' => 'Vo Zvolene', 'city' => 'Bratislava']);
        $quote = $this->makeQuote($tenant, $this->mixedItems());
        $this->assertStringContainsString('Vo Zvolene, dňa 3. 11. 2026', $this->text($this->render($quote, $tenant)));

        $tenant->update(['signing_place' => null]);
        $this->assertStringContainsString('Bratislava, dňa 3. 11. 2026', $this->text($this->render($quote, $tenant->refresh())));

        $tenant->update(['city' => null]);
        $this->assertStringContainsString('Dňa 3. 11. 2026', $this->text($this->render($quote, $tenant->refresh())));
    }

    public function test_orderer_prints_client_ico_and_dic(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '35683073', 'dic' => '2020905865']);
        $quote = $this->makeQuote($tenant, $this->mixedItems(), null, $client->id);

        $text = $this->text($this->render($quote, $tenant));

        $this->assertStringContainsString('IČO: 35683073', $text);
        $this->assertStringContainsString('DIČ: 2020905865', $text);
    }

    public function test_default_quote_prints_kegos_logo_and_uploaded_logo_replaces_it(): void
    {
        Storage::fake(config()->string('invoicing.logo.disk'));
        $tenant = Tenant::factory()->create();
        $quote = $this->makeQuote($tenant, $this->mixedItems());

        $this->assertStringContainsString('aria-label="Kegos Clean"', $this->render($quote, $tenant));

        $tenant->addMedia(UploadedFile::fake()->image('logo.png', 120, 40))->toMediaCollection('logo');
        $dataUri = app(TenantLogoResolver::class)->forTenant($tenant->refresh());

        $this->assertNotNull($dataUri);
        $html = $this->render($quote, $tenant, $dataUri);
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('aria-label="Kegos Clean"', $html);
    }

    public function test_logo_of_another_tenant_is_never_resolved(): void
    {
        Storage::fake(config()->string('invoicing.logo.disk'));
        Log::shouldReceive('warning')->once()->with('quote.logo.tenant_mismatch', Mockery::type('array'));
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->bindTenant($other);
        $other->addMedia(UploadedFile::fake()->image('logo.png', 120, 40))->toMediaCollection('logo');
        $foreign = $other->logoMedia();
        $this->assertNotNull($foreign);
        $foreign->forceFill(['tenant_id' => $tenant->id])->saveQuietly();

        $this->assertNull(app(TenantLogoResolver::class)->forTenant($other->refresh()));
    }

    public function test_invoice_header_partial_never_prints_firm_logo(): void
    {
        $html = View::make('pdf.partials.kegos-header', ['documentType' => 'Faktúra', 'number' => '1', 'logoDataUri' => 'data:image/png;base64,AAAA'])->render();

        $this->assertStringContainsString('aria-label="Kegos Clean"', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
