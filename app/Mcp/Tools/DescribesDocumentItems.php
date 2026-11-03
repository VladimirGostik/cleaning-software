<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Quotes\QuoteItemData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\QuoteBillingPeriodEnum;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;

trait DescribesDocumentItems
{
    /** @return array<string, mixed> */
    protected function quoteFields(Request $request, ?Quote $existing = null): array
    {
        $pick = fn (string $key, mixed $current = null): mixed => $request->has($key) ? $request->get($key) : $current;

        return [
            'client_id' => $pick('client_id', $existing?->client_id),
            'cleaning_object_id' => $pick('cleaning_object_id', $existing?->cleaning_object_id),
            'subject' => $pick('subject', $existing?->subject),
            'issue_date' => $pick('issue_date', $existing?->issue_date->toDateString()),
            'valid_until' => $pick('valid_until', $existing?->valid_until->toDateString()),
            'note' => $pick('note', $existing?->note),
            'included_text' => $pick('included_text', $existing !== null ? $existing->included_text : $this->firmDefaultIncludedText()),
            'customer_name' => $pick('customer_name', $existing?->customer_name),
            'customer_email' => $pick('customer_email', $existing?->customer_email),
            'customer_street' => $pick('customer_street', $existing?->customer_street),
            'customer_city' => $pick('customer_city', $existing?->customer_city),
            'customer_postal_code' => $pick('customer_postal_code', $existing?->customer_postal_code),
            'currency' => $pick('currency', $existing?->currency->value ?? CurrencyEnum::EUR->value),
            'items' => $request->has('items')
                ? $this->itemRows($request->get('items'), true)
                : ($existing?->items()->get()->map(fn ($item): array => QuoteItemData::fromModel($item)->toArray())->all() ?? []),
        ];
    }

    /** @return array<string, mixed> */
    protected function invoiceFields(Request $request, ?Invoice $existing = null): array
    {
        $pick = fn (string $key, mixed $current = null): mixed => $request->has($key) ? $request->get($key) : $current;
        $date = fn (?DateTimeInterface $value): ?string => $value?->format('Y-m-d');

        $preserved = $existing === null ? [] : [
            'deposit' => (float) $existing->deposit,
            'payment_type' => $existing->payment_type->value,
            'rounding_mode' => $existing->rounding_mode->value,
        ];

        return [
            ...$preserved,
            'client_id' => $pick('client_id', $existing?->client_id),
            'cleaning_object_id' => $pick('cleaning_object_id', $existing?->cleaning_object_id),
            'type' => $pick('type', $existing?->type->value ?? InvoiceTypeEnum::OneOff->value),
            'issue_date' => $pick('issue_date', $date($existing?->issue_date)),
            'delivery_date' => $pick('delivery_date', $date($existing?->delivery_date)),
            'due_date' => $pick('due_date', $date($existing?->due_date)),
            'period_from' => $pick('period_from', $date($existing?->period_from)),
            'period_to' => $pick('period_to', $date($existing?->period_to)),
            'customer_name' => $pick('customer_name', $existing?->customer_name),
            'customer_representative' => $existing?->customer_representative,
            'customer_ico' => $pick('customer_ico', $existing?->customer_ico),
            'customer_dic' => $existing?->customer_dic,
            'customer_vat_number' => $existing?->customer_vat_number,
            'customer_street' => $pick('customer_street', $existing?->customer_street),
            'customer_city' => $pick('customer_city', $existing?->customer_city),
            'customer_postal_code' => $pick('customer_postal_code', $existing?->customer_postal_code),
            'customer_country' => $existing?->customer_country,
            'customer_email' => $pick('customer_email', $existing?->customer_email),
            'note' => $pick('note', $existing?->note),
            'constant_symbol' => $existing?->constant_symbol,
            'specific_symbol' => $existing?->specific_symbol,
            'header_text' => $existing?->header_text,
            'footer_text' => $existing?->footer_text,
            'currency' => $pick('currency', $existing?->currency->value ?? CurrencyEnum::EUR->value),
            'items' => $request->has('items')
                ? $this->itemRows($request->get('items'), false)
                : ($existing?->items()->get()->map(fn ($item): array => InvoiceItemData::fromModel($item)->toArray())->all() ?? []),
        ];
    }

    /** @return array<string, Type> */
    protected function quoteProperties(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()->format('uuid')->nullable()->description('Existing client; then customer_* fields must be left out.'),
            'cleaning_object_id' => $schema->string()->format('uuid')->nullable()->description('Object of that client.'),
            'subject' => $schema->string()->nullable()->description('Quote subject line.'),
            'issue_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'valid_until' => $schema->string()->format('date')->description('YYYY-MM-DD, not before issue_date.'),
            'note' => $schema->string()->nullable()->description('Free note printed on the quote.'),
            'included_text' => $schema->string()->max(2000)->nullable()->description('"Included in the price" bullets, one per line. Omitted on create = the company default; omitted on update = unchanged.'),
            'customer_name' => $schema->string()->nullable()->description('Recipient name when there is no client_id.'),
            'customer_email' => $schema->string()->nullable()->description('Recipient e-mail.'),
            'customer_street' => $schema->string()->nullable(),
            'customer_city' => $schema->string()->nullable(),
            'customer_postal_code' => $schema->string()->nullable(),
            'currency' => $schema->string()->enum(array_map(fn (CurrencyEnum $case): string => $case->value, CurrencyEnum::cases()))->description('Defaults to EUR.'),
            'items' => $this->itemsProperty($schema, true),
        ];
    }

    /** @return array<string, Type> */
    protected function invoiceProperties(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->string()->format('uuid')->nullable()->description('Existing client; then customer_* fields are taken from the client.'),
            'cleaning_object_id' => $schema->string()->format('uuid')->nullable()->description('Object of that client.'),
            'type' => $schema->string()->enum(array_map(fn (InvoiceTypeEnum $case): string => $case->value, InvoiceTypeEnum::cases()))->description('monthly and special need period_from and period_to. Defaults to one_off.'),
            'issue_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'delivery_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD, not before issue_date.'),
            'period_from' => $schema->string()->format('date')->nullable()->description('Billing period start.'),
            'period_to' => $schema->string()->format('date')->nullable()->description('Billing period end.'),
            'customer_name' => $schema->string()->nullable()->description('Customer name when there is no client_id.'),
            'customer_ico' => $schema->string()->nullable(),
            'customer_street' => $schema->string()->nullable(),
            'customer_city' => $schema->string()->nullable(),
            'customer_postal_code' => $schema->string()->nullable(),
            'customer_email' => $schema->string()->nullable()->description('Needed before the invoice can be sent.'),
            'note' => $schema->string()->nullable(),
            'currency' => $schema->string()->enum(array_map(fn (CurrencyEnum $case): string => $case->value, CurrencyEnum::cases()))->description('Defaults to EUR.'),
            'items' => $this->itemsProperty($schema, false),
        ];
    }

    private function itemsProperty(JsonSchema $schema, bool $withNote): Type
    {
        $properties = [
            'description' => $schema->string()->required(),
            'quantity' => $schema->number()->min(0)->required(),
            'unit' => $schema->string()->nullable(),
            'unit_price' => $schema->number()->min(0)->description('Net unit price.')->required(),
            'discount_percent' => $schema->number()->min(0)->max(100),
            'vat_rate' => $schema->number()->min(0)->description('VAT rate in percent; 0 for non-VAT-payers.'),
        ];

        if ($withNote) {
            $properties['note'] = $schema->string()->max(500)->nullable();
            $properties['billing_period'] = $schema->string()
                ->enum(array_map(fn (QuoteBillingPeriodEnum $case): string => $case->value, QuoteBillingPeriodEnum::cases()))
                ->description('How the price is billed: one_off, monthly, quarterly or yearly. Defaults to one_off.');
        }

        return $schema->array()->items($schema->object($properties))->description('Line items; replaces all existing lines when given.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemRows(mixed $items, bool $withNote): array
    {
        $rows = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [
                'id' => null,
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit' => $item['unit'] ?? null,
                'unit_price' => $item['unit_price'] ?? null,
                'discount_percent' => $item['discount_percent'] ?? 0,
                'vat_rate' => $item['vat_rate'] ?? 0,
            ];

            if ($withNote) {
                $row['frequency'] = null;
                $row['note'] = $item['note'] ?? null;
                $row['billing_period'] = $item['billing_period'] ?? QuoteBillingPeriodEnum::OneOff->value;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function firmDefaultIncludedText(): ?string
    {
        return Tenant::query()->with('interface')->find(current_tenant_id())?->interface?->default_quote_included_text;
    }
}
