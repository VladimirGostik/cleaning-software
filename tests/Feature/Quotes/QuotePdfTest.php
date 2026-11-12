<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\QuoteKindEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\QuoteTextService;
use App\Services\Pdf\QuotePdfService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

final class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

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

    private function html(Quote $quote): string
    {
        return html_entity_decode(app(QuoteTextService::class)->printBody($quote));
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

    public function test_item_note_is_rendered_and_an_item_without_note_prints_no_note_line(): void
    {
        $tenant = Tenant::factory()->create();
        $with = $this->makeQuote($tenant, [new QuoteItemData(id: null, description: 'Cleaning service', frequency: null, quantity: 2, unit: 'hod', unit_price: 30, discount_percent: 0, vat_rate: 23, note: 'Watch out for the fragile vase')]);
        $without = $this->makeQuote($tenant, [$this->item('Cleaning service', QuoteBillingPeriodEnum::OneOff, 2, 30)]);

        $this->assertStringContainsString('Watch out for the fragile vase', $this->html($with));
        $this->assertStringContainsString('Cleaning service', $this->html($without));
        $this->assertStringNotContainsString('padding-right: 6mm', $this->html($without));
    }

    public function test_shell_prints_the_kegos_top_row_reference_and_body_container(): void
    {
        $html = View::make('pdf.quotes.default', ['title' => 'T', 'body' => '<p>x</p>', 'reference' => 'CP-1', 'showHeader' => true])->render();

        $this->assertStringContainsString('aria-label="Kegos Clean"', $html);
        $this->assertStringContainsString('viewBox="8 12 711.8 78"', $html);
        $this->assertStringContainsString('CP-1', $html);
        $this->assertStringContainsString('class="document-body document-body--quote"', $html);
        $this->assertStringNotContainsString('<img', $html);

        $hidden = View::make('pdf.quotes.default', ['title' => 'T', 'body' => '<p>x</p>', 'reference' => 'CP-1', 'showHeader' => false])->render();
        $this->assertStringNotContainsString('aria-label="Kegos Clean"', $hidden);
    }

    public function test_footer_prints_both_columns_and_is_empty_when_switched_off(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Quote Footer s.r.o.', 'ico' => '11223344']);
        $lines = DocumentFrame::quoteFooter($tenant);

        $footer = View::make('pdf.quotes.footer', ['show' => true, 'lines' => $lines])->render();
        $off = View::make('pdf.quotes.footer', ['show' => false, 'lines' => $lines])->render();

        $this->assertStringContainsString('Quote Footer s.r.o.', $footer);
        $this->assertStringContainsString('IČO 11223344', $footer);
        $this->assertStringNotContainsString('totalPages', $footer);
        $this->assertStringNotContainsString('Quote Footer s.r.o.', $off);
    }

    public function test_every_itemized_quote_renders_through_the_kegos_shell(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = $this->makeQuote($tenant, $this->mixedItems());
        $fake = Pdf::fake();

        app(QuotePdfService::class)->render($quote);

        $fake->assertViewIs('pdf.quotes.default');
        $fake->assertSee(['Ponuková cena', 'Window cleaning']);
    }

    public function test_default_text_prints_heading_subject_and_dates_in_the_kegos_order(): void
    {
        $tenant = Tenant::factory()->create();
        $quote = $this->makeQuote($tenant, $this->mixedItems());

        $text = $this->text($this->html($quote));

        $this->assertLessThan(strpos($text, 'Poskytovateľ'), strpos($text, 'Ponuková cena Office cleaning'));
        $this->assertStringContainsString('Dátum vystavenia: 3. 11. 2026 · Platnosť ponuky do: 3. 12. 2026', $text);
        $this->assertLessThan(strpos($text, 'Popis služby'), strpos($text, 'Ponuka služieb'));
        $this->assertStringContainsString('S pozdravom, '.$tenant->name, $text);
    }

    public function test_mixed_periods_render_cells_and_split_totals_without_combined_total(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $quote = $this->makeQuote($tenant, $this->mixedItems());

        $text = $this->text($this->html($quote));

        $this->assertStringContainsString('1× 300,00 EUR 300,00 EUR', $text);
        $this->assertStringContainsString('mesačne 70,00 EUR / mes. 70,00 EUR / mes.', $text);
        $this->assertStringContainsString('2× mesačne 25,00 EUR / upratovanie 50,00 EUR / mes.', $text);
        $this->assertStringContainsString('Jednorazovo 300,00 EUR', $text);
        $this->assertStringContainsString('Mesačne 120,00 EUR / mes.', $text);
        $this->assertStringNotContainsString('420,00', $text);
        $this->assertStringContainsString('Poskytovateľ nie je platiteľom DPH.', $text);
        $this->assertStringContainsString('Popis služby Množstvo Cena za jednotku Celková cena', $text);
    }

    public function test_one_off_only_quote_prints_spolu_instead_of_the_period(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $quote = $this->makeQuote($tenant, [
            $this->item('Window cleaning', QuoteBillingPeriodEnum::OneOff, 1, 300),
            $this->item('Carpets', QuoteBillingPeriodEnum::OneOff, 2, 50),
        ]);

        $text = $this->text($this->html($quote));

        $this->assertStringContainsString('Spolu 400,00 EUR', $text);
        $this->assertStringNotContainsString('Jednorazovo', $text);
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

        $text = $this->text($this->html($quote));

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

        $text = $this->text($this->html($quote));

        $this->assertStringContainsString('100,00 EUR, zľava 10 %', $text);
        $this->assertStringNotContainsString($quote->status->label(), $text);
    }

    public function test_included_section_only_when_text_has_lines(): void
    {
        $tenant = Tenant::factory()->create();
        $with = $this->makeQuote($tenant, $this->mixedItems(), "  Labour \n\n Material  \n");
        $without = $this->makeQuote($tenant, $this->mixedItems(), null);

        $textWith = $this->text($this->html($with));

        $this->assertStringContainsString('V cene je zahrnuté ● Labour ● Material', $textWith);
        $this->assertStringNotContainsString('V cene je zahrnuté', $this->text($this->html($without)));
    }

    public function test_closing_line_uses_signing_place_then_city_then_bare_date(): void
    {
        $tenant = Tenant::factory()->create(['signing_place' => 'Vo Zvolene', 'city' => 'Bratislava']);
        $quote = $this->makeQuote($tenant, $this->mixedItems());
        $this->assertStringContainsString('Vo Zvolene, dňa 3. 11. 2026', $this->text($this->html($quote)));

        $tenant->update(['signing_place' => null]);
        $this->assertStringContainsString('Bratislava, dňa 3. 11. 2026', $this->text($this->html($quote)));

        $tenant->update(['city' => null]);
        $this->assertStringContainsString('Dňa 3. 11. 2026', $this->text($this->html($quote)));
    }

    public function test_orderer_prints_client_ico_and_dic(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'ico' => '35683073', 'dic' => '2020905865']);
        $quote = $this->makeQuote($tenant, $this->mixedItems(), null, $client->id);

        $text = $this->text($this->html($quote));

        $this->assertStringContainsString('IČO: 35683073', $text);
        $this->assertStringContainsString('DIČ: 2020905865', $text);
    }

    public function test_note_is_printed_above_the_items_and_the_object_name_is_not_printed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Rajčianska 32']);
        $quote = $this->makeQuote($tenant, $this->mixedItems(), null, $client->id);
        $quote->update(['cleaning_object_id' => $object->id, 'note' => 'Cleaning on weekends only']);

        $text = $this->text($this->html($quote->load(['items', 'client', 'cleaningObject'])));

        $this->assertStringNotContainsString('Rajčianska 32', $text);
        $this->assertLessThan(strpos($text, 'Popis služby'), strpos($text, 'Cleaning on weekends only'));
    }

    public function test_invoice_header_partial_prints_the_system_logo(): void
    {
        $html = View::make('pdf.partials.kegos-header', ['documentType' => 'Faktúra', 'number' => '1'])->render();

        $this->assertStringContainsString('aria-label="Kegos Clean"', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_recurring_single_item_uses_the_short_period_suffix_in_both_price_cells(): void
    {
        $tenant = Tenant::factory()->create(['is_vat_payer' => false]);
        $quote = $this->makeQuote($tenant, [
            $this->item('Paušál', QuoteBillingPeriodEnum::Monthly, 1, 30, 'mesiac'),
            $this->item('Servis', QuoteBillingPeriodEnum::Monthly, 1, 40, 'paušál'),
            $this->item('Okná', QuoteBillingPeriodEnum::Yearly, 1, 90),
            $this->item('Dvakrát', QuoteBillingPeriodEnum::Monthly, 2, 25),
        ]);

        $text = $this->text($this->html($quote));

        $this->assertStringContainsString('ročne 90,00 EUR / rok 90,00 EUR / rok', $text);
        $this->assertStringContainsString('mesačne 30,00 EUR / mes. 30,00 EUR / mes.', $text);
        $this->assertStringContainsString('mesačne 40,00 EUR / paušál 40,00 EUR / mes.', $text);
        $this->assertStringContainsString('2× mesačne 25,00 EUR 50,00 EUR / mes.', $text);
        $this->assertStringNotContainsString('/ mesiac', $text);
    }
}
