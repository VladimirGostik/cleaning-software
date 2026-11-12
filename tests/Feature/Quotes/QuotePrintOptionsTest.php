<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Documents\QuoteTextService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QuotePrintOptionsTest extends TestCase
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
            'subject' => 'Ponuka',
            'issue_date' => '2026-11-11',
            'valid_until' => '2026-12-11',
            'note' => null,
            'items' => [
                ['id' => null, 'description' => 'Posyp', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
            'customer_name' => 'Zákazník',
            'number' => null,
            'kind' => 'itemized',
            'currency' => 'EUR',
        ], $overrides);
    }

    private function printed(Quote $quote): string
    {
        $html = html_entity_decode(app(QuoteTextService::class)->printBody($quote));

        return preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', strip_tags(str_replace('<', ' <', $html)))) ?? '';
    }

    /** @param  array<string, mixed>  $overrides */
    private function store(array $overrides = []): Quote
    {
        $this->post(route('quotes.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return Quote::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
    }

    public function test_totals_print_by_default_and_not_when_switched_off(): void
    {
        $this->actingAsTenantUser();
        Tenant::query()->update(['is_vat_payer' => false]);

        $on = $this->store();
        $this->assertTrue($on->show_totals);
        $this->assertTrue($on->show_vat_note);
        $this->assertStringContainsString('Spolu 10,00 EUR', $this->printed($on));
        $this->assertStringContainsString('Poskytovateľ nie je platiteľom DPH.', $this->printed($on));

        $off = $this->store(['show_totals' => false, 'show_vat_note' => false]);
        $text = $this->printed($off);
        $this->assertStringNotContainsString('Spolu', $text);
        $this->assertStringNotContainsString('platiteľom DPH', $text);
    }

    public function test_totals_off_keeps_the_included_list(): void
    {
        $this->actingAsTenantUser();
        Tenant::query()->update(['is_vat_payer' => false]);

        $text = $this->printed($this->store(['show_totals' => false, 'included_text' => "Práca\nMateriál"]));

        $this->assertStringContainsString('V cene je zahrnuté', $text);
        $this->assertStringContainsString('Materiál', $text);
        $this->assertStringNotContainsString('Spolu', $text);
    }

    public function test_vat_note_off_with_totals_on_prints_only_totals(): void
    {
        $this->actingAsTenantUser();
        Tenant::query()->update(['is_vat_payer' => false]);

        $text = $this->printed($this->store(['show_vat_note' => false]));

        $this->assertStringContainsString('Spolu 10,00 EUR', $text);
        $this->assertStringNotContainsString('platiteľom DPH', $text);
    }

    public function test_vat_payer_firm_never_prints_the_vat_sentence(): void
    {
        $this->actingAsTenantUser();
        Tenant::query()->update(['is_vat_payer' => true, 'vat_rate' => 23]);

        $text = $this->printed($this->store(['show_vat_note' => true]));

        $this->assertStringNotContainsString('platiteľom DPH', $text);
    }

    public function test_note_prints_above_the_items(): void
    {
        $this->actingAsTenantUser();

        $text = $this->printed($this->store(['note' => 'Len cez víkend']));

        $this->assertLessThan(strpos($text, 'Popis služby'), strpos($text, 'Len cez víkend'));
    }

    public function test_duplicate_copies_both_flags(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->store(['show_totals' => false, 'show_vat_note' => false]);

        $copy = app(QuoteService::class)->duplicate($quote);

        $this->assertFalse($copy->show_totals);
        $this->assertFalse($copy->show_vat_note);
    }
}
