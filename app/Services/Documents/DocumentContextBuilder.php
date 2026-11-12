<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Contracts\ContractGenerateData;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\QuoteBillingPeriodEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Support\CustomerParty;
use App\Support\Formats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

final readonly class DocumentContextBuilder
{
    public function __construct(
        private DocumentLocale $locale,
        private QuoteDisplay $quoteDisplay,
    ) {}

    public function forContract(
        CleaningObject|Client|TenantMembership $subject,
        Tenant $tenant,
        ContractGenerateData $terms,
        ?Quote $quote = null,
    ): DocumentRenderContext {
        return $this->locale->run(fn (): DocumentRenderContext => $this->buildContract($subject, $tenant, $terms, $quote));
    }

    public function forQuote(Quote $quote, Tenant $tenant): DocumentRenderContext
    {
        return $this->locale->run(fn (): DocumentRenderContext => $this->buildQuote($quote, $tenant));
    }

    public function sample(DocumentTypeEnum $type, ?EmploymentContractTypeEnum $employmentType): DocumentRenderContext
    {
        return $this->locale->run(fn (): DocumentRenderContext => $this->buildSample($type, $employmentType));
    }

    private function buildContract(
        CleaningObject|Client|TenantMembership $subject,
        Tenant $tenant,
        ContractGenerateData $terms,
        ?Quote $quote,
    ): DocumentRenderContext {
        $values = [
            ...$this->tenantValues($tenant),
            'contract.title' => $terms->title,
            'contract.valid_from' => $this->date($terms->valid_from),
            'contract.number' => $terms->number,
            'contract.end_date' => $this->date($terms->end_date),
            'date.today' => now()->format(Formats::DATE),
        ];

        $client = null;
        $object = null;
        $employee = null;

        if ($subject instanceof CleaningObject) {
            $subject->loadMissing('client');
            $object = $subject;
            $client = $subject->client;
        } elseif ($subject instanceof Client) {
            $client = $subject;
        } else {
            $subject->loadMissing('user');
            $employee = $subject;
        }

        if ($client !== null) {
            $client->loadMissing(['contacts']);
            $values = [...$values, ...$this->clientValues($client)];
        }

        if ($object !== null) {
            $values['object.name'] = $object->name;
            $values['object.address'] = $this->address($object->street, $object->postal_code, $object->city);
        }

        if ($employee !== null) {
            $values = [...$values, ...$this->employeeValues($employee)];
        }

        $employment = $terms->employment;

        if ($employment !== null) {
            $values['employment.type'] = $employment->employment_type->label();
            $values['employment.position'] = $employment->position;
            $values['employment.hourly_rate'] = $employment->hourly_rate !== null ? $this->decimal($employment->hourly_rate).' EUR' : null;
            $values['employment.monthly_salary'] = $employment->monthly_salary !== null ? $this->decimal($employment->monthly_salary).' EUR' : null;
            $values['employment.weekly_hours'] = $employment->weekly_hours !== null ? $this->decimal($employment->weekly_hours) : null;
            $values['employment.probation_end_date'] = $this->date($employment->probation_end_date);
        }

        if ($quote !== null) {
            $quote->loadMissing(['items', 'client', 'cleaningObject']);
            $values = [...$values, ...$this->quoteValues($quote, $tenant, $this->contractQuoteParty($quote))];
        }

        $flags = [
            'if.fixed_term' => $terms->term_type === ContractTermTypeEnum::Fixed,
            'if.indefinite_term' => $terms->term_type === ContractTermTypeEnum::Indefinite,
            'if.has_object' => $object !== null || $quote?->cleaning_object_id !== null,
            'if.vat_payer' => $tenant->is_vat_payer,
            'if.probation' => $employment?->probation_end_date !== null,
        ];

        $blocks = [
            'block.parties' => $this->partiesBlock($tenant, $client, $object, $employee),
            'block.signatures' => $this->signaturesBlock(),
        ];

        if ($employment !== null) {
            $blocks['block.employment_terms'] = $this->employmentTermsBlock($values);
        }

        if ($quote !== null) {
            $blocks['block.quote_items'] = $this->quoteItemsBlock($quote);
            $blocks['block.quote_totals'] = $this->quoteTotalsBlock($quote);
        }

        return new DocumentRenderContext($values, $flags, $blocks);
    }

    private function buildQuote(Quote $quote, Tenant $tenant): DocumentRenderContext
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject']);

        $values = [
            ...$this->tenantValues($tenant),
            ...$this->quoteValues($quote, $tenant, CustomerParty::forQuote($quote)),
            'date.today' => now()->format(Formats::DATE),
        ];

        if ($quote->cleaningObject !== null) {
            $values['object.name'] = $quote->cleaningObject->name;
            $values['object.address'] = $this->address($quote->cleaningObject->street, $quote->cleaningObject->postal_code, $quote->cleaningObject->city);
        }

        return new DocumentRenderContext(
            $values,
            [
                'if.vat_payer' => $quote->is_vat_payer,
                'if.has_object' => $quote->cleaning_object_id !== null,
            ],
            [
                'block.quote_parties' => $this->renderBlock('quote.parties', $this->quoteDisplay->partiesData($quote, $tenant)),
                'block.quote_note' => $this->renderBlock('quote.note', $this->quoteDisplay->noteData($quote) ?? []),
                'block.quote_items' => $this->renderBlock('quote.items', $this->quoteDisplay->itemsData($quote)),
                'block.quote_totals' => $this->renderQuoteTotals($quote),
                'block.signatures' => $this->signaturesBlock(),
            ],
        );
    }

    private function buildSample(DocumentTypeEnum $type, ?EmploymentContractTypeEnum $employmentType): DocumentRenderContext
    {
        $employmentLabel = ($employmentType ?? EmploymentContractTypeEnum::Tpp)->label();

        $values = [
            'tenant.name' => 'Ukážková s.r.o.',
            'tenant.address' => 'Hlavná 1, 811 01 Bratislava',
            'tenant.city' => 'Bratislava',
            'tenant.ico' => '12345678',
            'tenant.representative' => 'Ján Novák',
            'tenant.dic' => '2012345678',
            'tenant.vat_number' => 'SK2012345678',
            'tenant.iban' => 'SK89 7500 0000 0001 2345 6789',
            'tenant.contact_email' => 'info@example.com',
            'tenant.contact_phone' => '+421 900 000 000',
            'tenant.registration_info' => 'Obchodný register OS Bratislava I, oddiel Sro, vložka 12345/B',
            'contract.title' => 'Ukážková zmluva',
            'contract.valid_from' => now()->format(Formats::DATE),
            'contract.number' => '2026/001',
            'contract.end_date' => now()->addYear()->format(Formats::DATE),
            'contract.subject_scope' => 'pravidelné upratovanie spoločných priestorov dvakrát týždenne',
            'contract.price_terms' => '300,00 EUR mesačne',
            'contract.payment_terms' => 'mesačne, splatnosť 14 dní odo dňa doručenia faktúry',
            'contract.notice_period' => 'dva mesiace',
            'date.today' => now()->format(Formats::DATE),
            'client.name' => 'Ukážkový klient a.s.',
            'client.ico' => '87654321',
            'client.dic' => '2087654321',
            'client.vat_number' => 'SK2087654321',
            'client.address' => 'Obchodná 5, 811 06 Bratislava',
            'client.contact_name' => 'Eva Kováčová',
            'client.contact_email' => 'eva@example.com',
            'client.contact_phone' => '+421 900 111 111',
            'object.name' => 'Administratívna budova',
            'object.address' => 'Dlhá 10, 811 02 Bratislava',
            'employee.name' => 'Mária Ukážková',
            'employee.email' => 'maria@example.com',
            'employee.birth_date' => '1. 1. 1990',
            'employee.national_id' => '900101/1234',
            'employee.address' => 'Krátka 3, 811 03 Bratislava',
            'employee.phone' => '+421 900 222 222',
            'employee.position' => 'Upratovačka',
            'employee.ico' => '11223344',
            'employee.company_name' => 'Ukážka s.r.o.',
            'employment.type' => $employmentLabel,
            'employment.position' => 'Upratovačka',
            'employment.hourly_rate' => '6,50 EUR',
            'employment.monthly_salary' => '900,00 EUR',
            'employment.weekly_hours' => '40,00',
            'employment.probation_end_date' => now()->addMonths(3)->format(Formats::DATE),
            'employment.work_description' => 'upratovanie a čistenie priestorov podľa rozpisu prác',
            'employment.work_place' => 'Bratislava',
            'employment.reward_terms' => 'mesačná mzda 900,00 EUR',
            'employment.work_scope' => '40 hodín',
            'employment.work_schedule' => 'pondelok až piatok od 16:00 do 20:00',
            'employment.agreement_end_date' => now()->addMonths(6)->format(Formats::DATE),
            'customer.name' => 'Ukážkový zákazník',
            'customer.email' => 'zakaznik@example.com',
            'customer.address' => 'Stredná 7, 811 04 Bratislava',
            'quote.issue_date' => now()->format(Formats::DATE),
            'quote.valid_until' => now()->addDays(30)->format(Formats::DATE),
            'quote.total' => '300,00 EUR jednorazovo + 120,00 EUR mesačne',
            'quote.total_one_off' => '300,00 EUR',
            'quote.total_monthly' => '120,00 EUR',
            'quote.total_quarterly' => null,
            'quote.total_yearly' => null,
            'quote.number' => 'CP-2026-001',
            'quote.subject' => 'Pravidelné upratovanie',
            'quote.note' => 'Ceny sú uvedené bez DPH.',
            'quote.dated_line' => 'Bratislava, '.__('app.quote_pdf_dated').' '.now()->format(Formats::DATE),
            'customer.represented_by' => 'Správcovská s.r.o.',
            'customer.ico' => '35683073',
            'customer.dic' => '2020905865',
        ];

        $flags = [
            'if.fixed_term' => true,
            'if.indefinite_term' => false,
            'if.has_object' => true,
            'if.vat_payer' => true,
            'if.probation' => true,
        ];

        $blocks = [
            'block.parties' => $this->renderBlock('parties', [
                'parties' => [
                    ['heading' => __('app.contract_pdf_supplier'), 'lines' => [['text' => $values['tenant.name'], 'bold' => true], ['text' => $values['tenant.address'], 'bold' => false]]],
                    ['heading' => __('app.contract_pdf_party'), 'lines' => [['text' => $values['client.name'], 'bold' => true], ['text' => $values['object.address'], 'bold' => false]]],
                ],
            ]),
            'block.employment_terms' => $this->employmentTermsBlock($values),
            'block.signatures' => $this->signaturesBlock(),
            'block.quote_items' => $this->renderBlock('quote-items', [
                'headers' => $this->quoteItemHeaders(),
                'rows' => [
                    ['description' => 'Jednorazové umývanie okien', 'note' => 'Vrátane rámov a parapetov.', 'quantity' => '1×', 'unit_price' => '300,00 EUR', 'total' => '300,00 EUR'],
                    ['description' => 'Pravidelné upratovanie kancelárií', 'note' => null, 'quantity' => 'mesačne', 'unit_price' => '120,00 EUR / mes.', 'total' => '120,00 EUR / mes.'],
                ],
            ]),
            'block.quote_totals' => $this->renderBlock('quote-totals', [
                'rows' => [
                    ['label' => QuoteBillingPeriodEnum::OneOff->label(), 'value' => '300,00 EUR', 'bold' => true],
                    ['label' => QuoteBillingPeriodEnum::Monthly->label(), 'value' => '120,00 EUR / mes.', 'bold' => true],
                ],
            ]),
        ];

        if ($type === DocumentTypeEnum::Quote) {
            $blocks = [...$blocks, ...$this->quoteSampleBlocks($values)];
        }

        return new DocumentRenderContext($values, $flags, $blocks);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private function quoteSampleBlocks(array $values): array
    {
        $buyerLines = [
            __('app.quote_pdf_represented_by').':',
            $values['customer.represented_by'],
            'Stredná 7',
            '811 04 Bratislava',
            __('app.quote_pdf_ico').': '.$values['customer.ico'],
            __('app.quote_pdf_dic').': '.$values['customer.dic'],
        ];

        return [
            'block.quote_parties' => $this->renderBlock('quote.parties', [
                'provider' => ['label' => __('app.quote_pdf_provider'), 'name' => $values['tenant.name'], 'lines' => ['Hlavná 1', '811 01 Bratislava', __('app.quote_pdf_ico').': '.$values['tenant.ico'], __('app.quote_pdf_dic').': '.$values['tenant.dic']]],
                'buyer' => ['label' => __('app.quote_pdf_orderer'), 'name' => $values['customer.name'], 'lines' => $buyerLines],
                'dates' => [
                    ['label' => __('app.quote_pdf_issue_date'), 'value' => (string) $values['quote.issue_date']],
                    ['label' => __('app.quote_pdf_valid_until_long'), 'value' => (string) $values['quote.valid_until']],
                ],
            ]),
            'block.quote_note' => $this->renderBlock('quote.note', ['label' => __('app.quote_pdf_note'), 'text' => $values['quote.note']]),
            'block.quote_items' => $this->renderBlock('quote.items', [
                'headers' => [
                    'service' => __('app.quote_pdf_col_service'),
                    'quantity' => __('app.quote_pdf_item_quantity'),
                    'unit_price' => __('app.quote_pdf_col_unit_price'),
                    'total' => __('app.quote_pdf_col_line_total'),
                ],
                'rows' => [
                    ['description' => 'Jednorazové umývanie okien', 'note' => 'Vrátane rámov a parapetov.', 'quantity' => '1×', 'unit_price' => '300,00 EUR', 'amount' => '300,00 EUR', 'suffix' => null],
                    ['description' => 'Pravidelné upratovanie kancelárií', 'note' => null, 'quantity' => 'mesačne', 'unit_price' => '120,00 EUR / mes.', 'amount' => '120,00 EUR', 'suffix' => 'mes.'],
                ],
            ]),
            'block.quote_totals' => $this->renderBlock('quote.totals', [
                'included' => ['label' => __('app.quote_pdf_included'), 'lines' => ['Práca a čistiace prostriedky', 'Doprava']],
                'groups' => [
                    ['label' => QuoteBillingPeriodEnum::OneOff->label(), 'net' => null, 'vat' => null, 'total' => '300,00 EUR', 'suffix' => null],
                    ['label' => QuoteBillingPeriodEnum::Monthly->label(), 'net' => null, 'vat' => null, 'total' => '120,00 EUR', 'suffix' => 'mes.'],
                ],
                'labels' => ['excl_vat' => __('app.quote_pdf_excl_vat'), 'vat' => __('app.document_quote_vat')],
                'clause' => null,
            ]),
        ];
    }

    /** @return array<string, string|null> */
    private function tenantValues(Tenant $tenant): array
    {
        return [
            'tenant.name' => $tenant->name,
            'tenant.address' => $this->address($tenant->address_line, $tenant->postal_code, $tenant->city),
            'tenant.city' => $tenant->city,
            'tenant.ico' => $tenant->ico,
            'tenant.representative' => $tenant->representative_name,
            'tenant.dic' => $tenant->dic,
            'tenant.vat_number' => $tenant->vat_number,
            'tenant.iban' => $tenant->iban,
            'tenant.contact_email' => $tenant->contact_email,
            'tenant.contact_phone' => $tenant->contact_phone,
            'tenant.registration_info' => $tenant->registration_info,
        ];
    }

    /** @return array<string, string|null> */
    private function clientValues(Client $client): array
    {
        $contact = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();

        return [
            'client.name' => $client->name,
            'client.ico' => $client->ico,
            'client.dic' => $client->dic,
            'client.vat_number' => $client->vat_number,
            'client.address' => $this->address($client->street, $client->postal_code, $client->city),
            'client.contact_name' => $contact?->name,
            'client.contact_email' => $contact?->email,
            'client.contact_phone' => $contact?->phone,
        ];
    }

    /** @return array<string, string|null> */
    private function employeeValues(TenantMembership $employee): array
    {
        return [
            'employee.name' => $employee->display_name,
            'employee.email' => $employee->display_email,
            'employee.birth_date' => $employee->birth_date?->format(Formats::DATE),
            'employee.national_id' => $employee->national_id,
            'employee.address' => $this->address($employee->street, $employee->postal_code, $employee->city),
            'employee.phone' => $employee->phone,
            'employee.position' => $employee->position,
            'employee.ico' => $employee->company_ico,
            'employee.company_name' => $employee->company_name,
        ];
    }

    /** @return array<string, string|null> */
    private function quoteValues(Quote $quote, Tenant $tenant, CustomerParty $party): array
    {
        return [
            'customer.name' => $party->name,
            'customer.email' => $quote->customer_email,
            'customer.address' => $this->address($party->street, $party->postalCode, $party->city),
            'customer.represented_by' => $party->representedBy,
            'customer.ico' => $party->ico,
            'customer.dic' => $party->dic,
            'quote.issue_date' => $quote->issue_date->format(Formats::DATE),
            'quote.valid_until' => $quote->valid_until->format(Formats::DATE),
            'quote.total' => $this->quoteDisplay->totalsLine($quote),
            'quote.total_one_off' => $this->quoteDisplay->groupTotal($quote, QuoteBillingPeriodEnum::OneOff),
            'quote.total_monthly' => $this->quoteDisplay->groupTotal($quote, QuoteBillingPeriodEnum::Monthly),
            'quote.total_quarterly' => $this->quoteDisplay->groupTotal($quote, QuoteBillingPeriodEnum::Quarterly),
            'quote.total_yearly' => $this->quoteDisplay->groupTotal($quote, QuoteBillingPeriodEnum::Yearly),
            'quote.number' => $quote->number,
            'quote.subject' => $quote->subject,
            'quote.note' => $quote->note,
            'quote.dated_line' => $this->quoteDisplay->datedLine($quote, $tenant),
        ];
    }

    /** Contracts keep the client as the party, also when the quote itself prints a house. */
    private function contractQuoteParty(Quote $quote): CustomerParty
    {
        $entered = CustomerParty::fromQuote($quote);

        return $quote->client !== null ? CustomerParty::forInvoice(null, $quote->client, $entered) : $entered;
    }

    private function renderQuoteTotals(Quote $quote): string
    {
        $data = $this->quoteDisplay->totalsData($quote);

        return $data === null ? '' : $this->renderBlock('quote.totals', $data);
    }

    private function partiesBlock(Tenant $tenant, ?Client $client, ?CleaningObject $object, ?TenantMembership $employee): string
    {
        $supplier = [['text' => $tenant->name, 'bold' => true]];
        $supplier = [...$supplier, ...$this->lines([
            $tenant->address_line,
            $this->address(null, $tenant->postal_code, $tenant->city),
            $tenant->ico !== null ? 'IČO: '.$tenant->ico : null,
            $tenant->dic !== null ? 'DIČ: '.$tenant->dic : null,
        ])];

        $party = [];

        if ($employee !== null) {
            $party = [['text' => $employee->display_name, 'bold' => true], ...$this->lines([
                $employee->company_name,
                $employee->company_ico !== null ? 'IČO: '.$employee->company_ico : null,
                $employee->display_email,
            ])];
        } elseif ($client !== null) {
            $party = [['text' => $client->name, 'bold' => true]];

            if ($object !== null) {
                $party = [...$party, ...$this->lines([
                    $object->name,
                    $object->street,
                    $this->address(null, $object->postal_code, $object->city),
                ])];
            } else {
                $party = [...$party, ...$this->lines([
                    $client->street,
                    $this->address(null, $client->postal_code, $client->city),
                    $client->ico !== null ? 'IČO: '.$client->ico : null,
                    $client->dic !== null ? 'DIČ: '.$client->dic : null,
                ])];
            }
        }

        return $this->renderBlock('parties', [
            'parties' => [
                ['heading' => __('app.contract_pdf_supplier'), 'lines' => $supplier],
                ['heading' => __('app.contract_pdf_party'), 'lines' => $party],
            ],
        ]);
    }

    /** @param array<string, string|null> $values */
    private function employmentTermsBlock(array $values): string
    {
        $rows = [];

        foreach ([
            'employment.type' => 'app.contract_pdf_employment_type',
            'employment.position' => 'app.contract_pdf_position',
            'employment.monthly_salary' => 'app.contract_pdf_monthly_salary',
            'employment.hourly_rate' => 'app.contract_pdf_hourly_rate',
            'employment.weekly_hours' => 'app.contract_pdf_weekly_hours',
            'employment.probation_end_date' => 'app.contract_pdf_probation_until',
        ] as $key => $label) {
            if (($values[$key] ?? null) !== null && $values[$key] !== '') {
                $rows[] = ['label' => __($label), 'value' => $values[$key]];
            }
        }

        return $this->renderBlock('employment-terms', ['rows' => $rows]);
    }

    private function signaturesBlock(): string
    {
        return $this->renderBlock('signatures', [
            'left' => __('app.contract_pdf_signature_supplier'),
            'right' => __('app.contract_pdf_signature_party'),
        ]);
    }

    /** @return array<string, string> */
    private function quoteItemHeaders(): array
    {
        return [
            'description' => __('app.document_quote_description'),
            'quantity' => __('app.document_quote_quantity'),
            'unit_price' => __('app.document_quote_unit_price'),
            'total' => __('app.document_quote_line_total'),
        ];
    }

    private function quoteItemsBlock(Quote $quote): string
    {
        $rows = [];

        /** @var QuoteItem $item */
        foreach ($quote->items as $item) {
            $rows[] = [
                'description' => $item->description,
                'note' => $item->note,
                'quantity' => $this->quoteDisplay->quantity($item),
                'unit_price' => $this->quoteDisplay->unitPrice($quote, $item).$this->quoteDisplay->discount($item),
                'total' => $this->quoteDisplay->lineTotal($quote, $item),
            ];
        }

        return $this->renderBlock('quote-items', ['headers' => $this->quoteItemHeaders(), 'rows' => $rows]);
    }

    private function quoteTotalsBlock(Quote $quote): string
    {
        return $this->renderBlock('quote-totals', ['rows' => $this->quoteDisplay->totalRows($quote)]);
    }

    /**
     * @param  list<string|null>  $texts
     * @return list<array{text: string, bold: bool}>
     */
    private function lines(array $texts): array
    {
        $lines = [];

        foreach ($texts as $text) {
            if ($text !== null && trim($text) !== '') {
                $lines[] = ['text' => $text, 'bold' => false];
            }
        }

        return $lines;
    }

    /** @param array<string, mixed> $data */
    private function renderBlock(string $name, array $data): string
    {
        $html = View::make('documents.blocks.'.$name, $data)->render();

        return preg_replace('/>\s+</', '><', trim($html)) ?? $html;
    }

    private function address(?string $street, ?string $postalCode, ?string $city): ?string
    {
        $locality = trim(implode(' ', array_filter([$postalCode, $city])));
        $parts = array_filter([$street, $locality !== '' ? $locality : null]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function date(?string $date): ?string
    {
        return $date !== null ? Carbon::parse($date)->format(Formats::DATE) : null;
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, ',', "\u{00A0}");
    }
}
