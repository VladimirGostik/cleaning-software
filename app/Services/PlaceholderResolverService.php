<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Contracts\PlaceholderCatalogData;
use App\Data\Contracts\PlaceholderTokenData;
use App\Models\CleaningObject;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Support\Carbon;

/**
 * Resolves `{{token}}` placeholders in contract body text. Unknown tokens are left
 * verbatim (manual completion by the user), `null` values resolve to an empty string.
 */
final readonly class PlaceholderResolverService
{
    /**
     * @param  array<string, string|null>  $variables
     */
    public function resolve(string $body, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $body = str_replace('{{'.$key.'}}', $value ?? '', $body);
        }

        return $body;
    }

    /**
     * @return array<string, string|null>
     */
    public function variablesFor(
        CleaningObject|TenantMembership $contractable,
        Tenant $tenant,
        ContractUpsertData $data,
        ?Quote $quote = null,
    ): array {
        $variables = [
            'tenant.name' => $tenant->name,
            'tenant.ico' => $tenant->ico,
            'tenant.dic' => $tenant->dic,
            'tenant.address' => $this->joinAddress($tenant->address_line, $tenant->postal_code, $tenant->city),
            'tenant.iban' => $tenant->iban,
            'contract.title' => $data->title,
            'contract.valid_from' => Carbon::parse($data->valid_from)->format('d.m.Y'),
            'contract.end_date' => $data->end_date !== null ? Carbon::parse($data->end_date)->format('d.m.Y') : null,
        ];

        if ($contractable instanceof CleaningObject) {
            $contractable->loadMissing('client');

            $variables['client.name'] = $contractable->client?->name;
            $variables['client.ico'] = $contractable->client?->ico;
            $variables['client.dic'] = $contractable->client?->dic;
            $variables['object.name'] = $contractable->name;
            $variables['object.address'] = $this->joinAddress($contractable->street, $contractable->postal_code, $contractable->city);
        } else {
            $contractable->loadMissing('user');

            $name = trim(($contractable->first_name ?? '').' '.($contractable->last_name ?? ''));

            $variables['employee.name'] = $name !== '' ? $name : $contractable->user->name ?? null;
            $variables['employee.email'] = $contractable->user->email ?? null;
            $variables['employee.position'] = $contractable->position;
        }

        if ($quote !== null) {
            $quote->loadMissing('items');

            $variables['quote.number'] = $quote->number;
            $variables['quote.total'] = number_format((float) $quote->total, 2, ',', ' ').' '.$quote->currency->value;
            $variables['quote.items'] = $this->quoteItemsText($quote);
        }

        return $variables;
    }

    public function catalog(): PlaceholderCatalogData
    {
        $shared = [
            'tenant.name', 'tenant.ico', 'tenant.dic', 'tenant.address', 'tenant.iban',
            'contract.title', 'contract.valid_from', 'contract.end_date',
        ];

        $objectTokens = [...$shared, 'client.name', 'client.ico', 'client.dic', 'object.name', 'object.address', 'quote.number', 'quote.total', 'quote.items'];
        $membershipTokens = [...$shared, 'employee.name', 'employee.email', 'employee.position'];

        return new PlaceholderCatalogData(
            cleaning_object: array_map(fn (string $token) => $this->token($token), $objectTokens),
            tenant_membership: array_map(fn (string $token) => $this->token($token), $membershipTokens),
        );
    }

    private function token(string $token): PlaceholderTokenData
    {
        return new PlaceholderTokenData(
            token: '{{'.$token.'}}',
            label: __('app.contract_token_'.str_replace('.', '_', $token)),
        );
    }

    private function joinAddress(?string $street, ?string $postalCode, ?string $city): ?string
    {
        $line1 = $street;
        $line2 = trim(implode(' ', array_filter([$postalCode, $city])));

        $parts = array_filter([$line1, $line2 !== '' ? $line2 : null]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function quoteItemsText(Quote $quote): string
    {
        $lines = [];

        /** @var QuoteItem $item */
        foreach ($quote->items as $index => $item) {
            $description = $item->description;

            if ($item->frequency !== null && $item->frequency !== '') {
                $description .= " ({$item->frequency})";
            }

            if ($item->note !== null && $item->note !== '') {
                $description .= " — {$item->note}";
            }

            $unit = $item->unit ?? 'ks';

            $lines[] = sprintf(
                '%d. %s — %s %s × %s %s = %s %s',
                $index + 1,
                $description,
                number_format((float) $item->quantity, 2, ',', ' '),
                $unit,
                number_format((float) $item->unit_price, 2, ',', ' '),
                $quote->currency->value,
                number_format((float) $item->line_total, 2, ',', ' '),
                $quote->currency->value,
            );
        }

        $lines[] = __('app.contract_quote_items_total', [
            'total' => number_format((float) $quote->total, 2, ',', ' ').' '.$quote->currency->value,
        ]);

        return implode("\n", $lines);
    }
}
