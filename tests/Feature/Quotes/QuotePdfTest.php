<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteKindEnum;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
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
}
