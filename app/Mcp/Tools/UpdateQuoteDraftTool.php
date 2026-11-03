<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Quotes\QuotePeriodTotalsData;
use App\Data\Quotes\QuoteUpsertData;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update_quote_draft')]
#[Description('Changes a draft quote. Omitted fields stay as they are; items, when given, replace all lines. Only drafts are editable.')]
#[IsDestructive]
final class UpdateQuoteDraftTool extends TenantTool
{
    use DescribesDocumentItems;

    protected function rules(): array
    {
        return ['quote_id' => ['required', 'uuid']];
    }

    public function execute(Request $request, User $user, QuoteService $quotes): Response
    {
        $quote = Quote::query()->findOrFail($this->id($request, 'quote_id'));

        Gate::forUser($user)->authorize('update', $quote);

        $payload = [...$this->quoteFields($request, $quote), 'kind' => $quote->kind->value, 'number' => $quote->number];

        $data = $this->context->withRequest($user, $payload, ['quote' => $quote], fn (): QuoteUpsertData => QuoteUpsertData::validateAndCreate($payload));

        $updated = $quotes->update($quote, $data, $user, '');

        return $this->json(['id' => $updated->id, 'number' => $updated->number, 'status' => $updated->status->value, 'total' => $updated->total, 'period_totals' => QuotePeriodTotalsData::collectFrom($updated->period_totals)]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            'quote_id' => $schema->string()->format('uuid')->description('Draft quote id.')->required(),
            ...$this->quoteProperties($schema),
        ];
    }
}
