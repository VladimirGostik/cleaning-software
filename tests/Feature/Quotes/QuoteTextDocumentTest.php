<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\DocumentTypeEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Client;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Services\Documents\DocumentHtmlSanitizer;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\QuoteTextService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class QuoteTextDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('documents.disk'));
        Storage::fake('public');
    }

    /** @param  array<string, mixed>  $overrides  @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Ponuka',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [
                ['id' => null, 'description' => 'Pôvodná položka', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 23],
            ],
            'customer_name' => 'Zákazník s.r.o.',
            'customer_email' => null,
            'number' => null,
            'kind' => QuoteKindEnum::Itemized->value,
            'currency' => 'EUR',
        ], $overrides);
    }

    private function template(string $body): DocumentTemplate
    {
        return DocumentTemplate::factory()->global()->quote()->create(['body' => $body]);
    }

    public function test_draft_text_follows_the_items(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p><p>{{block.quote_items}}</p>');

        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]))->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();

        $this->assertStringContainsString('Pôvodná položka', (string) app(QuoteTextService::class)->printBody($quote));

        $this->put(route('quotes.update', $quote), $this->payload([
            'document_template_id' => $template->id,
            'items' => [['id' => null, 'description' => 'Zmenená položka', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 20, 'discount_percent' => 0, 'vat_rate' => 23]],
        ]))->assertSessionHasNoErrors();

        $body = (string) app(QuoteTextService::class)->printBody($quote->fresh() ?? $quote);
        $this->assertStringContainsString('Zmenená položka', $body);
        $this->assertStringNotContainsString('Pôvodná položka', $body);
        $this->assertStringContainsString('Zákazník s.r.o.', $body);
    }

    public function test_edit_carries_the_raw_body_and_show_the_resolved_one(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();

        $this->get(route('quotes.edit', $quote))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quote.text_document.body', '<p>{{customer.name}}</p>')
            ->has('context.templates', 1)
            ->has('context.tokens')
            ->has('context.letterhead'));
        $this->get(route('quotes.show', $quote))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quote.text_document.body', '<p>Zákazník s.r.o.</p>')
            ->where('quote.text_document.is_frozen', false)
            ->where('quote.text_document.missing_tokens', []));
    }

    public function test_text_is_frozen_at_send(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Pôvodný klient']);
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['client_id' => $client->id, 'customer_name' => null, 'document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();

        $this->post(route('quotes.send', $quote))->assertSessionHasNoErrors();
        $client->update(['name' => 'Premenovaný klient']);

        $quote = $quote->fresh() ?? $quote;
        $this->assertSame(QuoteStatusEnum::Sent, $quote->status);
        $this->assertSame('<p>Pôvodný klient</p>', app(QuoteTextService::class)->printBody($quote));
        $this->assertSame('<p>Pôvodný klient</p>', $quote->textDocument()?->body);
    }

    public function test_editing_a_sent_quote_reopens_it_as_a_draft_with_the_token_text(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Pôvodný klient']);
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['client_id' => $client->id, 'customer_name' => null, 'document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();
        $this->post(route('quotes.send', $quote))->assertSessionHasNoErrors();
        $client->update(['name' => 'Premenovaný klient']);

        $this->get(route('quotes.show', $quote))->assertInertia(fn (AssertableInertia $page) => $page->where('quote.is_editable', true));
        $this->get(route('quotes.edit', $quote))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quote.text_document.body', '<p>{{customer.name}}</p>')
            ->where('quote.text_document.is_frozen', false));

        $this->put(route('quotes.update', $quote), $this->payload([
            'client_id' => $client->id,
            'customer_name' => null,
            'document_template_id' => $template->id,
            'document_body' => '<p>{{customer.name}}</p>',
        ]))->assertSessionHasNoErrors();

        $quote = $quote->fresh() ?? $quote;
        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
        $this->assertNull($quote->sent_at);
        $this->assertNull($quote->textDocument()?->draft_body);
        $this->assertSame('<p>Premenovaný klient</p>', app(QuoteTextService::class)->printBody($quote));
    }

    public function test_send_is_refused_while_a_token_has_no_value(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{object.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();

        $this->post(route('quotes.send', $quote))->assertSessionHasErrors('status');
        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $status = $errors->first('status');
        $this->assertStringContainsString(__('app.document_token_object_name'), $status);
        $this->assertStringNotContainsString('object.name', $status);

        $this->assertSame(QuoteStatusEnum::Draft, $quote->fresh()?->status);
        $this->get(route('quotes.show', $quote))->assertInertia(fn (AssertableInertia $page) => $page->where('quote.text_document.missing_tokens', ['object.name']));
    }

    public function test_create_without_text_data_stores_the_firm_default_before_the_global_one(): void
    {
        $this->actingAsTenantUser();
        $global = $this->template('<p>global {{customer.name}}</p>');
        $own = DocumentTemplate::factory()->quote()->create(['tenant_id' => current_tenant_id(), 'name' => 'Vlastná', 'body' => '<p>own {{customer.name}}</p>']);

        $this->post(route('quotes.store'), $this->payload())->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();

        $this->assertSame('<p>own {{customer.name}}</p>', $quote->textDocument()?->body);
        $this->assertSame($own->id, $quote->textDocument()->document_template_id);
        $this->assertNotSame($global->id, $quote->textDocument()->document_template_id);
    }

    public function test_create_without_any_template_stores_the_catalogue_skeleton(): void
    {
        $this->actingAsTenantUser();

        $this->post(route('quotes.store'), $this->payload())->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();

        $this->assertSame(app(DocumentHtmlSanitizer::class)->sanitize(app(DocumentTemplateCatalogue::class)->skeleton(DocumentTypeEnum::Quote)), $quote->textDocument()?->body);
        $this->assertNull($quote->textDocument()->document_template_id);
        $this->get(route('quotes.show', $quote))->assertInertia(fn (AssertableInertia $page) => $page->where('quote.text_document.footer', fn (mixed $footer) => is_countable($footer) && count($footer) === 2));
    }

    public function test_update_without_text_data_keeps_body_template_and_frame_flags(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id, 'document_show_header' => false]));
        $quote = Quote::query()->firstOrFail();

        $this->put(route('quotes.update', $quote), $this->payload(['subject' => 'Zmenená']))->assertSessionHasNoErrors();

        $document = $quote->fresh()?->textDocument();
        $this->assertSame('<p>{{customer.name}}</p>', $document?->body);
        $this->assertSame($template->id, $document->document_template_id);
        $this->assertFalse((bool) $document->show_header);
    }

    public function test_update_without_text_data_on_a_sent_quote_restores_the_token_text_and_reopens_it(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();
        $this->post(route('quotes.send', $quote))->assertSessionHasNoErrors();

        $this->put(route('quotes.update', $quote), $this->payload(['customer_name' => 'Nový']))->assertSessionHasNoErrors();

        $quote = $quote->fresh() ?? $quote;
        $this->assertSame(QuoteStatusEnum::Draft, $quote->status);
        $this->assertSame('<p>{{customer.name}}</p>', $quote->textDocument()?->body);
        $this->assertNull($quote->textDocument()->draft_body);
        $this->assertStringContainsString('Nový', app(QuoteTextService::class)->printBody($quote));
    }

    public function test_quote_without_a_stored_text_prints_the_default_and_send_stores_then_freezes_it(): void
    {
        $this->actingAsTenantUser();
        $this->template('<p>{{customer.name}} {{quote.subject}}</p>');
        $quote = Quote::factory()->create(['tenant_id' => current_tenant_id(), 'customer_name' => 'Legacy', 'subject' => 'Starý']);
        $this->assertNull($quote->document);

        $this->assertSame('<p>Legacy Starý</p>', app(QuoteTextService::class)->printBody($quote));
        $this->get(route('quotes.show', $quote))->assertOk();

        $this->post(route('quotes.send', $quote))->assertSessionHasNoErrors();
        $quote = $quote->fresh() ?? $quote;
        $this->assertSame('<p>Legacy Starý</p>', $quote->textDocument()?->body);
        $this->assertSame('<p>{{customer.name}} {{quote.subject}}</p>', $quote->textDocument()->draft_body);
        $this->assertSame('<p>Legacy Starý</p>', app(QuoteTextService::class)->printBody($quote));
    }

    public function test_frozen_body_keeps_the_block_styles_after_send(): void
    {
        $this->actingAsTenantUser();
        $this->post(route('quotes.store'), $this->payload(['note' => 'Poznámka k ponuke']))->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();

        $this->post(route('quotes.send', $quote))->assertSessionHasNoErrors();

        $body = (string) $quote->fresh()?->textDocument()?->body;
        $this->assertStringContainsString('letter-spacing', $body);
        $this->assertStringContainsString('text-transform: uppercase', $body);
        $this->assertStringContainsString('Poznámka k ponuke', $body);
        $this->assertStringContainsString('<hr', $body);
    }

    public function test_frozen_text_is_untouched_by_a_later_template_edit(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();
        $this->post(route('quotes.send', $quote));

        $template->update(['body' => '<p>changed</p>']);

        $this->assertSame('<p>Zákazník s.r.o.</p>', app(QuoteTextService::class)->printBody($quote->fresh() ?? $quote));
    }

    public function test_custom_body_is_sanitized_and_unknown_tokens_rejected(): void
    {
        $this->actingAsTenantUser();

        $this->post(route('quotes.store'), $this->payload(['document_body' => '<p>a<script>x</script></p>']))->assertSessionHasNoErrors();
        $this->assertSame('<p>a</p>', Quote::query()->firstOrFail()->document?->body);

        $this->post(route('quotes.store'), $this->payload(['document_body' => '<p>{{employee.name}}</p>']))->assertSessionHasErrors('document_body');
    }

    public function test_duplicate_copies_a_draft_text_and_reseeds_a_sent_one_from_its_template(): void
    {
        $this->actingAsTenantUser();
        $template = $this->template('<p>{{customer.name}}</p>');
        $this->post(route('quotes.store'), $this->payload(['document_template_id' => $template->id]));
        $quote = Quote::query()->firstOrFail();

        $copy = app(QuoteService::class)->duplicate($quote);
        $this->assertSame('<p>{{customer.name}}</p>', $copy->textDocument()?->body);

        $this->post(route('quotes.send', $quote));
        $reseeded = app(QuoteService::class)->duplicate($quote->fresh() ?? $quote);
        $this->assertSame('<p>{{customer.name}}</p>', $reseeded->textDocument()?->body);

        $template->delete();
        $none = app(QuoteService::class)->duplicate($quote->fresh() ?? $quote);
        $this->assertNull($none->document);
    }

    public function test_uploaded_file_is_served_from_the_document(): void
    {
        $this->actingAsTenantUser();
        $uuid = (string) $this->postJson('/uploads', ['file' => UploadedFile::fake()->createWithContent('ponuka.pdf', "%PDF-1.4\nAAAA")])->json('uuid');

        $this->post(route('quotes.store'), $this->payload([
            'kind' => QuoteKindEnum::Document->value,
            'items' => [],
            'document_uuid' => $uuid,
        ]))->assertSessionHasNoErrors();
        $quote = Quote::query()->firstOrFail();

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
        $this->get(route('quotes.show', $quote))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('quote.document.file_name', 'ponuka.pdf')
            ->where('quote.text_document', null));
    }
}
