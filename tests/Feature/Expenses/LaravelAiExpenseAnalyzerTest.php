<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Data\Expenses\ExpenseAnalysisContextData;
use App\Data\Expenses\ExpenseCompanyContextData;
use App\Data\Expenses\ExpenseRowData;
use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Exceptions\AiUnavailableException;
use App\Services\Ai\AiClientOptions;
use App\Services\Ai\LaravelAiExpenseAnalyzer;
use App\Services\Ai\StructuredExtractionAgent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

final class LaravelAiExpenseAnalyzerTest extends TestCase
{
    private function context(): ExpenseAnalysisContextData
    {
        return new ExpenseAnalysisContextData(
            [new ExpenseCompanyContextData('c1', 'Alpha s.r.o.', '12345678', 'SK8975000000000123456789')],
            '2026-11-01',
        );
    }

    /** @return list<array<string, mixed>> */
    private function contentBlocks(Request $request): array
    {
        /** @var list<array<string, mixed>> */
        return data_get($request->data(), 'messages.0.content', []);
    }

    /** @param  array<string, mixed>  $structured */
    private function fakeProvider(array $structured): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($structured)]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ])]);
    }

    public function test_config_defaults(): void
    {
        $options = new AiClientOptions;

        $this->assertSame('claude-sonnet-5-5', $options->model);
        $this->assertSame('anthropic', $options->provider);
        $this->assertSame(30, $options->timeout);
        $this->assertSame(18, $options->assistantTimeout);
        $this->assertSame(100, config('ai.csv_preview_max_rows'));
        $this->assertSame(50, config('ai.categorize_batch'));
    }

    public function test_the_contract_resolves_to_the_laravel_ai_implementation_without_a_key(): void
    {
        config(['ai.providers.anthropic.key' => null]);

        $analyzer = app(AnalyzesExpenses::class);

        $this->assertInstanceOf(LaravelAiExpenseAnalyzer::class, $analyzer);
        $this->expectException(AiUnavailableException::class);

        $analyzer->analyzeText('x', $this->context());
    }

    public function test_every_method_fails_as_unavailable_without_a_key(): void
    {
        config(['ai.providers.anthropic.key' => '']);
        Http::fake();
        $analyzer = app(LaravelAiExpenseAnalyzer::class);

        foreach ([
            fn () => $analyzer->analyzeReceipt('%PDF', 'application/pdf', $this->context()),
            fn () => $analyzer->mapBankCsv(['a'], [['b']]),
            fn () => $analyzer->categorize([new ExpenseRowData(2, '1.00', 'x', null)], $this->context()),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected AiUnavailableException');
            } catch (AiUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
    }

    public function test_timeouts_and_provider_errors_become_unavailable(): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        $analyzer = app(LaravelAiExpenseAnalyzer::class);

        Http::fake(['api.anthropic.com/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);
        $this->assertUnavailable(fn () => $analyzer->analyzeText('x', $this->context()));

        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'boom']], 500)]);
        $this->assertUnavailable(fn () => $analyzer->analyzeText('x', $this->context()));

        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'slow']], 429)]);
        $this->assertUnavailable(fn () => $analyzer->mapBankCsv(['a'], [['b']]));
    }

    private function assertUnavailable(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_receipt_is_sent_as_a_document_with_structured_output_and_mapped_back(): void
    {
        $this->fakeProvider([
            'date' => '2026-10-02', 'amount' => '12.40', 'currency' => 'EUR', 'vat_amount' => '2.07',
            'supplier_name' => 'Metro', 'description' => 'Detergents', 'category' => 'cleaning_supplies',
            'company_id' => 'c1', 'confidence' => 0.8, 'reason' => 'Dodávateľ čistiacich prostriedkov',
        ]);

        $draft = app(LaravelAiExpenseAnalyzer::class)->analyzeReceipt('%PDF-1.4 data', 'application/pdf', $this->context());

        $this->assertSame('12.40', $draft->amount);
        $this->assertSame(CurrencyEnum::EUR, $draft->currency);
        $this->assertSame(ExpenseCategoryEnum::CleaningSupplies, $draft->proposal->category);
        $this->assertSame('c1', $draft->proposal->tenant_id);
        $this->assertSame(0.8, $draft->proposal->confidence);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $document = collect($this->contentBlocks($request))->firstWhere('type', 'document');

            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'test-key')
                && $body['model'] === 'claude-sonnet-5-5'
                && ! array_key_exists('tool_choice', $body)
                && data_get($body, 'output_config.format.type') === 'json_schema'
                && ($body['max_tokens'] ?? null) === 4096
                && data_get($document, 'source.media_type') === 'application/pdf'
                && data_get($document, 'source.data') === base64_encode('%PDF-1.4 data')
                && str_contains((string) json_encode($body['system'] ?? ''), 'untrusted data');
        });
    }

    public function test_every_schema_stays_within_what_native_structured_output_accepts(): void
    {
        $this->fakeProvider(['rows' => []]);
        $analyzer = app(LaravelAiExpenseAnalyzer::class);

        $analyzer->analyzeText('x', $this->context());
        $analyzer->mapBankCsv(['Dátum', 'Suma'], [['01.10.2026', '-5,00']]);
        $analyzer->categorize([new ExpenseRowData(0, '5.00', 'Shell', null)], $this->context());

        Http::assertSentCount(3);
        Http::assertSent(function (Request $request): bool {
            $this->assertSchemaIsAccepted(data_get($request->data(), 'output_config.format.schema'));

            return true;
        });
    }

    private function assertSchemaIsAccepted(mixed $node): void
    {
        $this->assertIsArray($node);

        foreach (['minimum', 'maximum'] as $unsupported) {
            $this->assertArrayNotHasKey($unsupported, $node);
        }

        if (is_array($node['type'] ?? null) && in_array('null', $node['type'], true)) {
            $this->assertArrayNotHasKey('enum', $node);
        }

        foreach ([...array_values((array) ($node['properties'] ?? [])), ...(isset($node['items']) ? [$node['items']] : [])] as $child) {
            $this->assertSchemaIsAccepted($child);
        }
    }

    public function test_images_are_sent_as_image_blocks(): void
    {
        $this->fakeProvider(['currency' => 'EUR', 'confidence' => 0.1]);

        $draft = app(LaravelAiExpenseAnalyzer::class)->analyzeReceipt('binary', 'image/png', $this->context());

        $this->assertNull($draft->amount);
        $this->assertNull($draft->proposal->category);
        Http::assertSent(fn (Request $request): bool => collect($this->contentBlocks($request))->contains(fn (array $block): bool => $block['type'] === 'image'));
    }

    public function test_garbage_model_output_degrades_to_empty_fields(): void
    {
        $this->fakeProvider(['currency' => 'XXX', 'category' => 'luxury', 'confidence' => 'high', 'amount' => ['nested']]);

        $draft = app(LaravelAiExpenseAnalyzer::class)->analyzeText('x', $this->context());

        $this->assertSame(CurrencyEnum::EUR, $draft->currency);
        $this->assertNull($draft->proposal->category);
        $this->assertNull($draft->amount);
        $this->assertSame(0.0, $draft->proposal->confidence);
    }

    public function test_bank_csv_mapping_and_categorisation_are_mapped(): void
    {
        $this->fakeProvider([
            'date_column' => 'Dátum', 'date_format' => 'd.m.Y', 'amount_column' => 'Suma', 'counterparty_column' => 'Príjemca',
            'note_column' => null, 'reference_column' => 'VS', 'decimal_separator' => ',',
        ]);

        $mapping = app(LaravelAiExpenseAnalyzer::class)->mapBankCsv(['Dátum', 'Suma'], [['01.10.2026', '-5,00']]);

        $this->assertSame('Suma', $mapping->amount_column);
        $this->assertNull($mapping->note_column);
        $this->assertSame(',', $mapping->decimal_separator);
    }

    public function test_categorisation_ignores_malformed_items(): void
    {
        $this->fakeProvider(['rows' => [
            ['row' => 2, 'category' => 'fuel', 'confidence' => 0.9],
            ['row' => 3, 'category' => 'nonsense', 'confidence' => 5],
            ['row' => 'x', 'category' => 'rent', 'confidence' => 1],
            'junk',
        ]]);

        $proposals = app(LaravelAiExpenseAnalyzer::class)->categorize(
            [new ExpenseRowData(2, '1.00', 'Shell', null), new ExpenseRowData(3, '2.00', 'Zzz', null)],
            $this->context(),
        );

        $this->assertCount(2, $proposals);
        $this->assertSame(ExpenseCategoryEnum::Fuel, $proposals[0]->category);
        $this->assertNull($proposals[1]->category);
        $this->assertSame(1.0, $proposals[1]->confidence);
    }

    public function test_tool_use_reply_named_output_structured_data_is_parsed(): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'output_structured_data', 'input' => [
                'date' => '2026-10-03', 'amount' => '9.90', 'currency' => 'CZK', 'category' => 'fuel', 'confidence' => 0.5,
            ]]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $draft = app(LaravelAiExpenseAnalyzer::class)->analyzeText('x', $this->context());

        $this->assertSame('9.90', $draft->amount);
        $this->assertSame(CurrencyEnum::CZK, $draft->currency);
        $this->assertSame(ExpenseCategoryEnum::Fuel, $draft->proposal->category);
    }

    public function test_calls_carry_the_configured_model_and_a_thirty_second_timeout(): void
    {
        config(['ai.providers.anthropic.key' => 'test-key']);
        StructuredExtractionAgent::fake([['currency' => 'EUR', 'confidence' => 0.1]]);

        app(LaravelAiExpenseAnalyzer::class)->analyzeText('x', $this->context());

        StructuredExtractionAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 30 && $prompt->model === 'claude-sonnet-5-5');
    }
}
