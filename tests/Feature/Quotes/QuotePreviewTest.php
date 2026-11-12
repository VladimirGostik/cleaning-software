<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QuotePreviewTest extends TestCase
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
            'note' => 'Len cez víkend',
            'items' => [
                ['id' => null, 'description' => 'Posyp', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
            'customer_name' => 'Zákazník',
            'customer_represented_by' => 'Správca',
            'number' => null,
            'kind' => 'itemized',
            'currency' => 'EUR',
        ], $overrides);
    }

    private function quote(): Quote
    {
        $quote = Quote::factory()->numbered()->create(['tenant_id' => current_tenant_id()]);
        QuoteItem::factory()->create(['tenant_id' => $quote->tenant_id, 'quote_id' => $quote->id]);

        return $quote;
    }

    public function test_saved_quote_preview_is_an_inline_pdf_that_is_not_cached(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->quote();

        $response = $this->get(route('quotes.preview', $quote));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_saved_quote_preview_needs_view_quotes(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->quote();
        $this->actingAs(User::factory()->create());

        $this->get(route('quotes.preview', $quote))->assertForbidden();
    }

    public function test_document_kind_preview_streams_the_stored_file_inline(): void
    {
        $this->actingAsTenantUser();
        $quote = Quote::factory()->document()->create(['tenant_id' => current_tenant_id()]);
        $quote->document()->create(['tenant_id' => $quote->tenant_id, 'type' => 'quote', 'title' => 'x'])
            ->addMediaFromString("%PDF-1.4\nAAAA")->usingFileName('ponuka.pdf')->toMediaCollection('file', config()->string('documents.disk'));

        $response = $this->get(route('quotes.preview', $quote));

        $response->assertOk();
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_unsaved_form_pdf_and_text_preview_write_nothing(): void
    {
        $this->actingAsTenantUser();

        $this->post(route('quotes.preview-pdf'), $this->payload())->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $json = $this->postJson(route('quotes.text-preview'), $this->payload())->assertOk();

        $this->assertSame(0, Quote::query()->count());
        /** @var array<string, string> $values */
        $values = $json->json('values');
        /** @var array<string, string> $blocks */
        $blocks = $json->json('blocks');
        $this->assertSame('Zákazník', $values['customer.name']);
        $this->assertSame('Správca', $values['customer.represented_by']);
        $this->assertStringContainsString('Posyp', $blocks['block.quote_items']);
        $this->assertStringContainsString('Len cez víkend', $blocks['block.quote_note']);
        $this->assertSame([], $json->json('missing_tokens'));
    }

    public function test_existing_quote_form_previews_use_the_update_routes_and_change_nothing(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->quote();
        $before = $quote->fresh()?->subject;

        $this->post(route('quotes.preview-pdf.existing', $quote), $this->payload(['number' => $quote->number, 'subject' => 'Iný']))->assertOk();
        $json = $this->postJson(route('quotes.text-preview.existing', $quote), $this->payload(['number' => $quote->number, 'subject' => 'Iný']))->assertOk();
        /** @var array<string, string> $values */
        $values = $json->json('values');
        $this->assertSame('Iný', $values['quote.subject']);

        $this->assertSame($before, $quote->fresh()?->subject);
        $this->assertNull($quote->fresh()?->document);
    }

    public function test_incomplete_payload_answers_422_on_both_kinds(): void
    {
        $this->actingAsTenantUser();

        $this->postJson(route('quotes.preview-pdf'), $this->payload(['items' => []]))->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson(route('quotes.text-preview'), $this->payload(['items' => []]))->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_document_kind_payload_is_refused_on_kind(): void
    {
        $this->actingAsTenantUser();

        $this->postJson(route('quotes.text-preview'), $this->payload(['kind' => 'document', 'items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('kind');
    }

    public function test_form_previews_are_forbidden_without_the_right(): void
    {
        $this->actingAsTenantUser();
        $quote = $this->quote();
        $this->actingAs(User::factory()->create());

        $this->post(route('quotes.preview-pdf'), $this->payload())->assertForbidden();
        $this->postJson(route('quotes.text-preview'), $this->payload())->assertForbidden();
        $this->post(route('quotes.preview-pdf.existing', $quote), $this->payload())->assertForbidden();
        $this->postJson(route('quotes.text-preview.existing', $quote), $this->payload())->assertForbidden();
    }
}
