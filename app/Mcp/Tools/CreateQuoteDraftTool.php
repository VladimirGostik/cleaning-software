<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Data\Quotes\QuotePeriodTotalsData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\QuoteKindEnum;
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

#[Name('create_quote_draft')]
#[Description('Creates an itemized quote as a draft. Sending it is a separate two-step action (prepare_action send_quote).')]
#[IsDestructive(false)]
final class CreateQuoteDraftTool extends TenantTool
{
    use DescribesDocumentItems;

    public function execute(Request $request, User $user, QuoteService $quotes): Response
    {
        Gate::forUser($user)->authorize('create', Quote::class);

        $data = QuoteUpsertData::validateAndCreate([
            ...$this->quoteFields($request),
            'kind' => QuoteKindEnum::Itemized->value,
            'number' => null,
        ]);

        $quote = $quotes->create($data, $user, '');

        return $this->json(['id' => $quote->id, 'number' => $quote->number, 'status' => $quote->status->value, 'total' => $quote->total, 'period_totals' => QuotePeriodTotalsData::collectFrom($quote->period_totals)]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->companyProperty($schema),
            ...$this->quoteProperties($schema),
        ];
    }
}
