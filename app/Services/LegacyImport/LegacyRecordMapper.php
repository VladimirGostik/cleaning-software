<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Data\Tenants\TenantSupplierProfileData;
use App\Enums\ClientTypeEnum;
use App\Enums\CustomerPartySourceEnum;
use App\Enums\ObjectTypeEnum;
use App\Enums\RecurringBilledMonthEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Support\CustomerParty;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class LegacyRecordMapper
{
    public const array IMPORTED_FIRM_IDS = [2, 3, 23, 25, 100];

    public const string DEFAULT_CITY = 'Bratislava';

    private const string REPRESENTED_LINE = '/^\s*(v\s*zast|v\.\s*z\.|zast[uú]pen)/iu';

    private const string REPRESENTED_PREFIX = '/^\s*(?:v\s*zast\p{L}*\.?|v\.\s*z\.|zast[uú]pen\p{L}*\.?)\s*[:.]?\s*/iu';

    private const string STAND_SUFFIX = '/\s*kont\.\s*stojisko\s*$/iu';

    /**
     * @param  array<string, mixed>  $firm
     * @return array<string, mixed>
     */
    public function firmInput(array $firm): array
    {
        $profile = $this->supplierProfile($firm);

        return [
            'name' => $this->resolve($firm, 'name', 'company_name') ?? '',
            'ico' => $this->compact($this->resolve($firm, 'ico', 'company_ico')) ?? '',
            'dic' => $profile->dic,
            'vat_number' => null,
            'is_vat_payer' => false,
            'address_line' => $profile->address_line,
            'city' => $profile->city,
            'postal_code' => $profile->postal_code,
            'country' => 'SK',
            'contact_email' => null,
            'contact_phone' => null,
            'iban' => $profile->iban,
            'swift_bic' => null,
            'color' => null,
            'copy_settings' => false,
        ];
    }

    /** @param  array<string, mixed>  $firm */
    public function supplierProfile(array $firm): TenantSupplierProfileData
    {
        return new TenantSupplierProfileData(
            address_line: $this->resolve($firm, 'street', 'company_address'),
            city: $this->resolve($firm, 'city', 'city'),
            postal_code: $this->postalCode($this->resolve($firm, 'postal_code', 'postal_code')),
            country: 'SK',
            dic: $this->compact($firm['company_dic'] ?? null),
            iban: $this->iban($firm['company_iban'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<int|string, mixed>  $clientIcDph
     * @return array<string, mixed>
     */
    public function clientInput(array $client, array $clientIcDph): array
    {
        $ico = $this->compact($this->resolve($client, 'ico', 'company_ico'));
        $vatNumber = $this->compact($clientIcDph[$this->text($client['id'] ?? null)] ?? null);

        return [
            'type' => $ico !== null ? ClientTypeEnum::Corporate->value : ClientTypeEnum::Private->value,
            'name' => $this->resolve($client, 'name', 'company_name') ?? '',
            'ico' => $ico,
            'dic' => $this->compact($client['company_dic'] ?? null),
            'vat_number' => $vatNumber,
            'is_vat_payer' => $vatNumber !== null,
            'street' => $this->resolve($client, 'street', 'company_address'),
            'city' => $this->resolve($client, 'city', 'city'),
            'postal_code' => $this->postalCode($this->resolve($client, 'postal_code', 'postal_code')),
            'country' => 'SK',
            'note' => null,
            'contacts' => [],
            'peppol_participant_id' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @return list<array{template_id: int, merged_template_ids: list<int>, firm: int, client: int, name: string, type: ObjectTypeEnum, street: ?string, city: string, billing_name: ?string}>
     */
    public function objects(array $templates): array
    {
        return $this->plan($templates)['objects'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{template_id: int, merged_template_ids: list<int>, firm: int, client: int, name: string, type: ObjectTypeEnum, street: ?string, city: string, billing_name: ?string}>
     */
    public function manualObjects(array $rows): array
    {
        $objects = [];

        foreach ($rows as $row) {
            if (! $this->isImportedFirm($row)) {
                continue;
            }

            $name = trim($this->text($row['object_name'] ?? null)) ?: trim($this->text($row['name'] ?? null));
            $type = ObjectTypeEnum::tryFrom($this->text($row['object_type'] ?? null))
                ?? throw new RuntimeException('manual recurring #'.$this->legacyId($row['id'] ?? null).': invalid object_type.');

            $objects[] = [...$this->object($row, $name, $name, $type), 'merged_template_ids' => []];
        }

        return $objects;
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @param  list<array<string, mixed>>  $manual
     * @return list<array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, manual: bool, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}>
     */
    public function recurringRows(array $templates, array $manual): array
    {
        $targets = $this->plan($templates)['targets'];
        $rows = [];

        foreach ($templates as $template) {
            if (! $this->isImportedFirm($template)) {
                continue;
            }

            $id = $this->legacyId($template['id']);
            $name = trim($this->text($template['name'] ?? null));
            $rows[] = $this->recurringRow($template, $id, $targets[$id] ?? $name, $name, false);
        }

        foreach ($manual as $row) {
            if (! $this->isImportedFirm($row)) {
                continue;
            }

            $name = trim($this->text($row['name'] ?? null));
            $objectName = trim($this->text($row['object_name'] ?? null)) ?: $name;
            $rows[] = $this->recurringRow($row, $this->legacyId($row['id'] ?? null), $objectName, $name, true);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{ref: int, firm: int, object_name: string, name: string}>
     */
    public function retiredRows(array $rows): array
    {
        $retired = [];

        foreach ($rows as $row) {
            if (! $this->isImportedFirm($row)) {
                continue;
            }

            $name = trim($this->text($row['name'] ?? null));
            $retired[] = [
                'ref' => $this->legacyId($row['id'] ?? null),
                'firm' => $this->legacyId($row['firm']),
                'object_name' => trim($this->text($row['object_name'] ?? null)) ?: $name,
                'name' => $name,
            ];
        }

        return $retired;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array{id: int, firm: int, client: ?int, monthly_template: ?int, number: string, status: 'created'|'sent'|'paid', name: string, issue_date: CarbonImmutable, due_date: CarbonImmutable, delivery_date: CarbonImmutable, billed_month: CarbonImmutable, payment_date: ?CarbonImmutable, header_text: ?string, buyer: CustomerParty, supplier: array<string, ?string>, items: list<array{description: string, quantity: float, unit_price: float}>}>
     */
    public function invoiceRows(array $rows): array
    {
        $invoices = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('invoice: row must be an object.');
            }

            /** @var array<string, mixed> $row */
            $id = $this->legacyId($row['id'] ?? null);
            $label = "invoice #{$id}";
            $number = trim($this->text($row['number'] ?? null));
            $status = $this->text($row['status'] ?? null);

            if ($id <= 0 || $number === '' || $this->legacyId($row['firm'] ?? null) <= 0) {
                throw new RuntimeException("{$label}: id, firm and number are required.");
            }

            if (! in_array($status, ['created', 'sent', 'paid'], true)) {
                throw new RuntimeException("{$label}: invalid status.");
            }

            $issueDate = $this->invoiceDate($row['issue_date'] ?? null) ?? throw new RuntimeException("{$label}: invalid issue_date.");
            $dueDate = $this->invoiceDate($row['due_date'] ?? null) ?? throw new RuntimeException("{$label}: invalid due_date.");
            $paymentDate = $this->invoiceDate($row['payment_date'] ?? null);

            if ($status === 'paid' && $paymentDate === null) {
                throw new RuntimeException("{$label}: paid without payment_date.");
            }

            $billingMonth = $this->legacyId($row['billing_month'] ?? null);

            if ($billingMonth < 1 || $billingMonth > 12) {
                throw new RuntimeException("{$label}: invalid billing_month.");
            }

            $billedMonth = $issueDate->startOfMonth()->setMonth($billingMonth);

            if ($billingMonth > $issueDate->month) {
                $billedMonth = $billedMonth->subYear();
            }

            $buyer = $this->invoiceBuyer($row);

            if ($buyer->name === null) {
                throw new RuntimeException("{$label}: buyer name is empty.");
            }

            $items = $this->invoiceItems($row['items'] ?? null, $label);
            $name = trim($this->text($row['name'] ?? null));
            $headerText = $this->headerText($this->text($row['above'] ?? null), $this->text($row['services'] ?? null));

            $invoices[] = [
                'id' => $id,
                'firm' => $this->legacyId($row['firm']),
                'client' => $this->legacyId($row['client'] ?? null) ?: null,
                'monthly_template' => $this->legacyId($row['monthly_template'] ?? null) ?: null,
                'number' => $number,
                'status' => $status,
                'name' => $name,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'delivery_date' => $this->invoiceDate($row['delivery_date'] ?? null) ?? $issueDate,
                'billed_month' => $billedMonth,
                'payment_date' => $paymentDate,
                'header_text' => $headerText === null ? null : str_replace(RecurringBilledMonthEnum::TOKEN, RecurringBilledMonthEnum::formatMonth($billedMonth), $headerText),
                'buyer' => $buyer,
                'supplier' => $this->invoiceSupplier($row),
                'items' => $items,
            ];
        }

        return $invoices;
    }

    /** @param  array<string, mixed>  $row */
    public function invoiceBuyer(array $row): CustomerParty
    {
        $buyer = is_array($row['buyer'] ?? null) ? $row['buyer'] : [];
        $header = [];

        foreach ([1, 2, 3, 4] as $position) {
            $header[$position] = $this->line($row['header'.$position] ?? null);
        }

        $residential = $this->line($buyer['name'] ?? null);
        $represented = null;

        foreach ([$header[2], $residential, $header[3]] as $candidate) {
            if ($candidate !== null && preg_match(self::REPRESENTED_LINE, $candidate) === 1) {
                $represented = $candidate;

                break;
            }
        }

        if ($header[1] !== null) {
            $billingHeaders = $header;
            $billingHeaders[2] = $header[2] === $represented ? null : $header[2];
            $name = $this->billingName($billingHeaders);
        } elseif ($header[4] !== null && $residential !== null && $residential === $represented) {
            $name = $header[4];
        } elseif ($residential !== null && $residential !== $represented) {
            $name = $residential;
        } elseif ($header[2] !== null && $header[2] !== $represented) {
            $name = $header[2];
        } else {
            $name = trim($this->text($row['name'] ?? null));
        }

        $street = $this->line($buyer['street'] ?? null);
        $postalCode = null;
        $city = null;

        if ($street !== null) {
            $postalCode = $this->postalCode($this->line($buyer['postal_code'] ?? null));
            $city = $this->line($buyer['city'] ?? null);
        } else {
            $street = $header[3] === $represented ? null : $header[3];

            if ($header[4] !== null && preg_match('/^(\d{3})\s?(\d{2})\s+(\S.*)$/u', $header[4], $match) === 1) {
                $postalCode = $match[1].' '.$match[2];
                $city = trim($match[3]);
            }
        }

        return new CustomerParty(
            name: $name === null || $name === '' ? null : mb_substr($name, 0, 255),
            representedBy: $represented === null ? null : $this->line(preg_replace(self::REPRESENTED_PREFIX, '', $represented)),
            ico: $this->compact($buyer['ico'] ?? null),
            dic: $this->compact($buyer['dic'] ?? null),
            vatNumber: $this->compact($buyer['ic_dph'] ?? null),
            street: $street,
            city: $city,
            postalCode: $postalCode,
            country: 'SK',
            source: CustomerPartySourceEnum::Entered,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, ?string>
     */
    public function invoiceSupplier(array $row): array
    {
        $supplier = is_array($row['supplier'] ?? null) ? $row['supplier'] : [];

        return [
            'supplier_name' => $this->line($supplier['name'] ?? null),
            'supplier_address_line' => $this->line($supplier['street'] ?? null),
            'supplier_city' => $this->line($supplier['city'] ?? null),
            'supplier_postal_code' => $this->postalCode($this->line($supplier['postal_code'] ?? null)),
            'supplier_ico' => $this->compact($supplier['ico'] ?? null),
            'supplier_dic' => $this->compact($supplier['dic'] ?? null),
            'supplier_vat_number' => $this->compact($supplier['ic_dph'] ?? null),
            'supplier_iban' => $this->iban($supplier['iban'] ?? null),
            'supplier_country' => 'SK',
        ];
    }

    /** @return list<array{description: string, quantity: float, unit_price: float}> */
    private function invoiceItems(mixed $raw, string $label): array
    {
        $items = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            $description = is_array($item) ? trim($this->text($item['name'] ?? null)) : '';

            if (! is_array($item) || $description === '') {
                throw new RuntimeException("{$label}: item without a name.");
            }

            $items[] = [
                'description' => mb_substr($description, 0, 255),
                'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0,
                'unit_price' => is_numeric($item['price'] ?? null) ? (float) $item['price'] : 0.0,
            ];
        }

        if ($items === []) {
            throw new RuntimeException("{$label}: no items.");
        }

        return $items;
    }

    private function invoiceDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $match) !== 1 || ! checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $match[1], (int) $match[2], (int) $match[3])->startOfDay();
    }

    private function line(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }

    /** @return array{year: int, last: int} */
    public function legacyNumber(string $value): array
    {
        if (preg_match('/^(\d{4})(\d{4})$/', $value, $match) !== 1) {
            throw new RuntimeException('Invalid legacy invoice number (expected YYYYNNNN).');
        }

        return ['year' => (int) $match[1], 'last' => (int) $match[2]];
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @return array{objects: list<array{template_id: int, merged_template_ids: list<int>, firm: int, client: int, name: string, type: ObjectTypeEnum, street: ?string, city: string, billing_name: ?string}>, targets: array<int, string>}
     */
    private function plan(array $templates): array
    {
        $objects = [];
        $index = [];
        $stands = [];
        $merged = [];
        $targets = [];

        foreach ($templates as $template) {
            if (! $this->isImportedFirm($template)) {
                continue;
            }

            $name = trim($this->text($template['name'] ?? null));
            $base = $this->standBase($name);

            if ($base !== null) {
                $stands[] = [$template, $name, $base];

                continue;
            }

            $index[$this->objectKey($template, $name)] ??= count($objects);
            $objects[] = $this->object($template, $name, $name, $this->houseType($name));
            $targets[$this->legacyId($template['id'])] = $name;
        }

        foreach ($stands as [$template, $name, $base]) {
            $target = $index[$this->objectKey($template, $base)] ?? null;

            if ($target !== null) {
                $merged[$target][] = $this->legacyId($template['id']);
                $targets[$this->legacyId($template['id'])] = $objects[$target]['name'];

                continue;
            }

            $objects[] = $this->object($template, $name, $base, ObjectTypeEnum::CommonAreas);
            $targets[$this->legacyId($template['id'])] = $name;
        }

        return [
            'objects' => array_map(
                fn (array $object, int $position): array => [...$object, 'merged_template_ids' => $merged[$position] ?? []],
                $objects,
                array_keys($objects),
            ),
            'targets' => $targets,
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, manual: bool, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}
     */
    private function recurringRow(array $source, int $ref, string $objectName, string $name, bool $manual): array
    {
        $items = [];

        foreach (is_array($source['items'] ?? null) ? $source['items'] : [] as $item) {
            if (is_array($item) && trim($this->text($item['name'] ?? null)) !== '') {
                $items[] = [
                    'description' => trim($this->text($item['name'])),
                    'quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0,
                    'unit_price' => is_numeric($item['price'] ?? null) ? (float) $item['price'] : 0.0,
                ];
            }
        }

        return [
            'ref' => $ref,
            'firm' => $this->legacyId($source['firm']),
            'client' => $this->legacyId($source['client']),
            'object_name' => $objectName,
            'name' => $name,
            'frequency' => $this->frequency($source['frequency'] ?? null, $ref, $manual),
            'manual' => $manual,
            'last_billed_month' => $this->billedMonth($source['last_billed_month'] ?? null),
            'header_text' => $this->headerText($this->text($source['above'] ?? null), $this->text($source['services'] ?? null)),
            'items' => $items,
        ];
    }

    private function frequency(mixed $value, int $ref, bool $manual): RecurringFrequencyEnum
    {
        if (! $manual && ($value === null || $value === '')) {
            return RecurringFrequencyEnum::Monthly;
        }

        return RecurringFrequencyEnum::tryFrom($this->text($value))
            ?? throw new RuntimeException("recurring #{$ref}: invalid frequency.");
    }

    private function billedMonth(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $match) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $match[1], (int) $match[2], 1)->startOfDay();
    }

    private function headerText(string $above, string $services): ?string
    {
        $above = rtrim($above);
        $services = trim($services);

        if ($services !== '' && ! str_contains(mb_strtolower($above), mb_strtolower($services))) {
            $above = $above === '' ? $services : $above."\n".$services;
        }

        return $above === '' ? null : $above;
    }

    /**
     * @param  list<array<string, mixed>>  $templates
     * @param  list<array<string, mixed>>  $firmClients2026
     * @param  list<array<string, mixed>>  $manual
     * @return list<array{firm: int, client: int}>
     */
    public function clientPairs(array $templates, array $firmClients2026, array $manual = []): array
    {
        $pairs = [];

        foreach ([...$templates, ...$firmClients2026, ...$manual] as $row) {
            if (! $this->isImportedFirm($row)) {
                continue;
            }

            $pairs[$this->legacyId($row['firm']).'-'.$this->legacyId($row['client'])] ??= ['firm' => $this->legacyId($row['firm']), 'client' => $this->legacyId($row['client'])];
        }

        return array_values($pairs);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array{template_id: int, merged_template_ids: list<int>, firm: int, client: int, name: string, type: ObjectTypeEnum, street: ?string, city: string, billing_name: ?string}
     */
    private function object(array $template, string $name, string $base, ObjectTypeEnum $type): array
    {
        $headers = [];

        foreach ([1, 2, 3, 4] as $position) {
            $value = $template['header'.$position] ?? null;
            $headers[$position] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return [
            'template_id' => $this->legacyId($template['id']),
            'merged_template_ids' => [],
            'firm' => $this->legacyId($template['firm']),
            'client' => $this->legacyId($template['client']),
            'name' => $name,
            'type' => $type,
            'street' => $this->street($base),
            'city' => self::DEFAULT_CITY,
            'billing_name' => $this->billingName($headers),
        ];
    }

    /** @param  array<int, string|null>  $headers */
    private function billingName(array $headers): ?string
    {
        if ($headers[1] !== null) {
            $name = $headers[1];
            $next = $headers[2];

            if ($next !== null && preg_match('/^\p{Ll}/u', $next) === 1 && preg_match('/^(v\s*zast|vz\.)/iu', $next) !== 1) {
                $name .= ' '.$next;
            }
        } else {
            $name = $headers[4];
        }

        return $name === null ? null : mb_substr(trim($name), 0, 255);
    }

    private function street(string $base): ?string
    {
        if (preg_match('/č\./iu', $base) !== 1) {
            return null;
        }

        $street = preg_replace('/\s+/u', ' ', (string) preg_replace('/\s*č\.\s*/iu', ' ', $base));

        return trim((string) $street) === '' ? null : trim((string) $street);
    }

    private function houseType(string $name): ObjectTypeEnum
    {
        return preg_match('/č\./iu', $name) === 1 ? ObjectTypeEnum::ResidentialBuilding : ObjectTypeEnum::Office;
    }

    public function standBase(string $name): ?string
    {
        if (preg_match(self::STAND_SUFFIX, $name) !== 1) {
            return null;
        }

        $base = trim((string) preg_replace(self::STAND_SUFFIX, '', $name));

        return $base === '' ? null : $base;
    }

    /** @param  array<string, mixed>  $template */
    private function objectKey(array $template, string $name): string
    {
        return $this->legacyId($template['firm']).'-'.$this->legacyId($template['client']).'-'.mb_strtolower($name);
    }

    /** @param  array<string, mixed>  $row */
    private function isImportedFirm(array $row): bool
    {
        return in_array($this->legacyId($row['firm']), self::IMPORTED_FIRM_IDS, true);
    }

    /** @param  array<string, mixed>  $record */
    private function resolve(array $record, string $registerKey, string $legacyKey): ?string
    {
        $register = $record['register'] ?? null;

        foreach ([is_array($register) ? ($register[$registerKey] ?? null) : null, $record[$legacyKey] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function iban(mixed $value): ?string
    {
        $iban = $this->compact($value);

        return $iban === null ? null : strtoupper($iban);
    }

    public function legacyId(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function compact(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $compact = preg_replace('/\s+/u', '', $value);

        return $compact === null || $compact === '' ? null : $compact;
    }

    private function postalCode(?string $value): ?string
    {
        $digits = $this->compact($value);

        if ($digits === null) {
            return null;
        }

        return preg_match('/^(\d{3})(\d{2})$/', $digits, $match) === 1 ? $match[1].' '.$match[2] : $value;
    }
}
