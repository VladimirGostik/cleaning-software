<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuotePeriodTotalsData;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_quote')]
#[Description('One quote with customer, dates, totals and line items.')]
#[IsReadOnly]
final class GetQuoteTool extends TenantTool
{
    protected function rules(): array
    {
        return ['quote_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user): Response
    {
        $quote = Quote::query()->with(['items', 'client:id,name'])->findOrFail($this->id($request, 'quote_id'));

        Gate::forUser($user)->authorize('view', $quote);

        return $this->json([
            'id' => $quote->id,
            'number' => $quote->number,
            'status' => $quote->status->value,
            'kind' => $quote->kind->value,
            'subject' => $quote->subject,
            'client_id' => $quote->client_id,
            'customer_name' => $quote->client->name ?? $quote->customer_name,
            'customer_email' => $quote->customer_email,
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until->toDateString(),
            'currency' => $quote->currency->value,
            'subtotal' => $quote->subtotal,
            'vat_amount' => $quote->vat_amount,
            'total' => $quote->total,
            'period_totals' => QuotePeriodTotalsData::collectFrom($quote->period_totals),
            'included_text' => $quote->included_text,
            'note' => $quote->note,
            'items' => $quote->items->map(fn ($item): array => QuoteItemData::fromModel($item)->toArray())->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'quote_id' => $schema->string()->format('uuid')->description('Quote id from list_quotes.')->required(),
        ];
    }
}
