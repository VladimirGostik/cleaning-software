<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Data\Tenants\TenantSupplierProfileData;
use App\Enums\ClientTypeEnum;
use App\Enums\ObjectTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class LegacyRecordMapper
{
    public const array IMPORTED_FIRM_IDS = [2, 3, 23, 25, 100];

    public const string DEFAULT_CITY = 'Bratislava';

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
     * @return list<array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}>
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
     * @return array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}
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

    private function standBase(string $name): ?string
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
