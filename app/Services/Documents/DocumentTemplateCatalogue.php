<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\DocumentTemplates\DocumentTokenCatalogData;
use App\Data\DocumentTemplates\DocumentTokenData;
use App\Enums\DocumentTokenGroupEnum as Group;
use App\Enums\DocumentTokenKindEnum as Kind;
use App\Enums\DocumentTypeEnum;

final readonly class DocumentTemplateCatalogue
{
    public const string TOKEN_PATTERN = '/\{\{\s*([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)\s*\}\}/i';

    /** @return array<string, array{Kind, Group}> */
    private static function definitions(): array
    {
        return [
            'tenant.name' => [Kind::Text, Group::Tenant],
            'tenant.address' => [Kind::Text, Group::Tenant],
            'tenant.city' => [Kind::Text, Group::Tenant],
            'tenant.ico' => [Kind::Text, Group::Tenant],
            'tenant.representative' => [Kind::Text, Group::Tenant],
            'tenant.dic' => [Kind::Optional, Group::Tenant],
            'tenant.vat_number' => [Kind::Optional, Group::Tenant],
            'tenant.iban' => [Kind::Optional, Group::Tenant],
            'tenant.contact_email' => [Kind::Optional, Group::Tenant],
            'tenant.contact_phone' => [Kind::Optional, Group::Tenant],
            'tenant.registration_info' => [Kind::Optional, Group::Tenant],
            'contract.title' => [Kind::Text, Group::Contract],
            'contract.valid_from' => [Kind::Text, Group::Contract],
            'contract.number' => [Kind::Optional, Group::Contract],
            'contract.end_date' => [Kind::Optional, Group::Contract],
            'contract.subject_scope' => [Kind::Text, Group::Contract],
            'contract.price_terms' => [Kind::Text, Group::Contract],
            'contract.payment_terms' => [Kind::Text, Group::Contract],
            'contract.notice_period' => [Kind::Text, Group::Contract],
            'date.today' => [Kind::Text, Group::Contract],
            'client.name' => [Kind::Text, Group::Client],
            'client.ico' => [Kind::Optional, Group::Client],
            'client.dic' => [Kind::Optional, Group::Client],
            'client.vat_number' => [Kind::Optional, Group::Client],
            'client.address' => [Kind::Optional, Group::Client],
            'client.contact_name' => [Kind::Optional, Group::Client],
            'client.contact_email' => [Kind::Optional, Group::Client],
            'client.contact_phone' => [Kind::Optional, Group::Client],
            'object.name' => [Kind::Text, Group::Object],
            'object.address' => [Kind::Text, Group::Object],
            'employee.name' => [Kind::Text, Group::Employee],
            'employee.email' => [Kind::Text, Group::Employee],
            'employee.birth_date' => [Kind::Text, Group::Employee],
            'employee.national_id' => [Kind::Text, Group::Employee],
            'employee.address' => [Kind::Text, Group::Employee],
            'employee.phone' => [Kind::Optional, Group::Employee],
            'employee.position' => [Kind::Optional, Group::Employee],
            'employee.ico' => [Kind::Optional, Group::Employee],
            'employment.type' => [Kind::Text, Group::Employment],
            'employment.position' => [Kind::Optional, Group::Employment],
            'employment.hourly_rate' => [Kind::Optional, Group::Employment],
            'employment.monthly_salary' => [Kind::Optional, Group::Employment],
            'employment.weekly_hours' => [Kind::Optional, Group::Employment],
            'employment.probation_end_date' => [Kind::Optional, Group::Employment],
            'employment.work_description' => [Kind::Text, Group::Employment],
            'employment.work_place' => [Kind::Text, Group::Employment],
            'employment.reward_terms' => [Kind::Text, Group::Employment],
            'employment.work_scope' => [Kind::Text, Group::Employment],
            'employment.work_schedule' => [Kind::Text, Group::Employment],
            'employment.agreement_end_date' => [Kind::Text, Group::Employment],
            'customer.name' => [Kind::Text, Group::Customer],
            'customer.email' => [Kind::Optional, Group::Customer],
            'customer.address' => [Kind::Optional, Group::Customer],
            'quote.issue_date' => [Kind::Text, Group::Quote],
            'quote.valid_until' => [Kind::Text, Group::Quote],
            'quote.total' => [Kind::Text, Group::Quote],
            'quote.number' => [Kind::Optional, Group::Quote],
            'quote.subject' => [Kind::Optional, Group::Quote],
            'quote.note' => [Kind::Optional, Group::Quote],
            'if.fixed_term' => [Kind::Flag, Group::Conditions],
            'if.indefinite_term' => [Kind::Flag, Group::Conditions],
            'if.has_object' => [Kind::Flag, Group::Conditions],
            'if.vat_payer' => [Kind::Flag, Group::Conditions],
            'if.probation' => [Kind::Flag, Group::Conditions],
            'block.parties' => [Kind::Block, Group::Blocks],
            'block.employment_terms' => [Kind::Block, Group::Blocks],
            'block.signatures' => [Kind::Block, Group::Blocks],
            'block.quote_items' => [Kind::Block, Group::Blocks],
            'block.quote_totals' => [Kind::Block, Group::Blocks],
        ];
    }

    public function kindOf(string $key): ?Kind
    {
        return (self::definitions()[$key] ?? null)[0] ?? null;
    }

    public function labelOf(string $key): string
    {
        $lang = 'app.document_token_'.str_replace('.', '_', $key);
        $label = __($lang);

        return $label === $lang ? $key : $label;
    }

    /** @param  list<string>  $keys */
    public function labelsOf(array $keys): string
    {
        return implode(', ', array_map($this->labelOf(...), $keys));
    }

    /** @return list<DocumentTokenData> */
    public function forType(DocumentTypeEnum $type): array
    {
        $tokens = [];

        foreach (self::definitions() as $key => [$kind, $group]) {
            if ($this->isAvailable($type, $key, $group)) {
                $tokens[] = new DocumentTokenData($key, $this->labelOf($key), $kind, $group);
            }
        }

        return $tokens;
    }

    /** @return list<string> */
    public function keysForType(DocumentTypeEnum $type): array
    {
        return array_map(static fn (DocumentTokenData $token): string => $token->key, $this->forType($type));
    }

    public function catalog(): DocumentTokenCatalogData
    {
        return new DocumentTokenCatalogData(
            service_agreement: $this->forType(DocumentTypeEnum::ServiceAgreement),
            employment: $this->forType(DocumentTypeEnum::Employment),
            nda: $this->forType(DocumentTypeEnum::Nda),
            gdpr: $this->forType(DocumentTypeEnum::Gdpr),
            other: $this->forType(DocumentTypeEnum::Other),
            quote: $this->forType(DocumentTypeEnum::Quote),
        );
    }

    public function skeleton(DocumentTypeEnum $type, bool $withQuoteBlocks = false): string
    {
        if ($type === DocumentTypeEnum::Quote) {
            return '<p>{{block.quote_items}}</p><p>{{block.quote_totals}}</p><p>{{block.signatures}}</p>';
        }

        $html = '<p>{{block.parties}}</p>';

        if ($type === DocumentTypeEnum::Employment) {
            $html .= '<p>{{block.employment_terms}}</p>';
        }

        if ($withQuoteBlocks) {
            $html .= '<p>{{block.quote_items}}</p><p>{{block.quote_totals}}</p>';
        }

        return $html.'<p>{{block.signatures}}</p>';
    }

    private function isAvailable(DocumentTypeEnum $type, string $key, Group $group): bool
    {
        if ($type === DocumentTypeEnum::Quote) {
            return match ($group) {
                Group::Tenant, Group::Customer, Group::Object, Group::Quote => true,
                Group::Conditions => in_array($key, ['if.vat_payer', 'if.has_object'], true),
                Group::Contract => $key === 'date.today',
                Group::Blocks => in_array($key, ['block.quote_items', 'block.quote_totals', 'block.signatures'], true),
                default => false,
            };
        }

        $base = match ($group) {
            Group::Tenant, Group::Contract, Group::Conditions => true,
            Group::Blocks => in_array($key, ['block.parties', 'block.signatures'], true),
            default => false,
        };

        if ($base) {
            return true;
        }

        $isServiceAgreement = $type === DocumentTypeEnum::ServiceAgreement;
        $isEmployment = $type === DocumentTypeEnum::Employment;
        $isGeneric = in_array($type, [DocumentTypeEnum::Nda, DocumentTypeEnum::Gdpr, DocumentTypeEnum::Other], true);

        return match ($group) {
            Group::Client, Group::Object => $isServiceAgreement || $isGeneric,
            Group::Quote => $isServiceAgreement,
            Group::Employee, Group::Employment => $isEmployment || $isGeneric,
            Group::Blocks => match ($key) {
                'block.quote_items', 'block.quote_totals' => $isServiceAgreement,
                'block.employment_terms' => $isEmployment || $isGeneric,
                default => false,
            },
            default => false,
        };
    }
}
