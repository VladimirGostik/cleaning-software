<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Data\Clients\ClientUpsertData;
use App\Data\Objects\ObjectUpsertData;
use App\Data\Tenants\AddTenantData;
use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ToolContext;
use App\Services\ClientService;
use App\Services\InvoiceSettingsService;
use App\Services\ObjectService;
use App\Services\RegistrationService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\LaravelData\Data;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final readonly class LegacyImportService
{
    private const array REQUIRED_KEYS = ['firms', 'clients', 'firm_clients_2026', 'monthly_templates'];

    public function __construct(
        private DatabaseManager $db,
        private RegistrationService $registration,
        private ClientService $clients,
        private ObjectService $objects,
        private InvoiceSettingsService $settings,
        private ToolContext $context,
        private LegacyRecordMapper $mapper,
        private LegacyRecurringInvoiceImporter $recurringInvoices,
        private LegacyInvoiceHistoryImporter $invoiceHistory,
        private PermissionRegistrar $permissions,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function import(array $payload, User $owner, ?Tenant $settingsSource, bool $dryRun): LegacyImportReport
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                throw new RuntimeException("Invalid legacy file: key \"{$key}\" must be an array.");
            }
        }

        if (isset($payload['invoices']) && ! is_array($payload['invoices'])) {
            throw new RuntimeException('Invalid legacy file: key "invoices" must be an array.');
        }

        $previousTeam = $this->permissions->getPermissionsTeamId();

        try {
            return $this->execute($payload, $owner, $settingsSource, $dryRun);
        } finally {
            $this->permissions->setPermissionsTeamId($previousTeam);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function execute(array $payload, User $owner, ?Tenant $settingsSource, bool $dryRun): LegacyImportReport
    {
        $report = new LegacyImportReport;
        $report->dryRun = $dryRun;

        $this->db->beginTransaction();

        try {
            $tenants = $this->importFirms($payload, $owner, $settingsSource, $report);

            if ($dryRun) {
                $this->reportMedia($settingsSource, $tenants, $report, 'would_copy');
                $this->db->rollBack();
                $this->permissions->forgetCachedPermissions();

                return $report;
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->permissions->forgetCachedPermissions();

            throw $e;
        }

        $this->copyMedia($owner, $settingsSource, $tenants, $report);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, Tenant>
     */
    private function importFirms(array $payload, User $owner, ?Tenant $settingsSource, LegacyImportReport $report): array
    {
        /** @var list<array<string, mixed>> $templates */
        $templates = $payload['monthly_templates'];
        /** @var list<array<string, mixed>> $firmClients */
        $firmClients = $payload['firm_clients_2026'];
        /** @var list<array<string, mixed>> $legacyClients */
        $legacyClients = $payload['clients'];
        /** @var array<int|string, mixed> $icDph */
        $icDph = is_array($payload['client_ic_dph'] ?? null) ? $payload['client_ic_dph'] : [];

        /** @var list<array<string, mixed>> $manual */
        $manual = is_array($payload['manual_recurring'] ?? null) ? array_values($payload['manual_recurring']) : [];
        /** @var list<array<string, mixed>> $retired */
        $retired = is_array($payload['retired_recurring'] ?? null) ? array_values($payload['retired_recurring']) : [];
        $lastNumbers = is_array($payload['last_invoice_number_2026'] ?? null) ? $payload['last_invoice_number_2026'] : [];

        $invoiceRows = $this->mapper->invoiceRows(is_array($payload['invoices'] ?? null) ? array_values($payload['invoices']) : []);
        $postalCodes = $this->postalCodeMap($payload);
        $pairs = $this->mapper->clientPairs($templates, $firmClients, $manual);
        $objects = [...$this->mapper->objects($templates), ...$this->mapper->manualObjects($manual)];
        $recurringRows = $this->mapper->recurringRows($templates, $manual);
        $retiredRows = $this->mapper->retiredRows($retired);
        $tenants = [];

        /** @var list<array<string, mixed>> $firms */
        $firms = $payload['firms'];

        foreach ($firms as $firm) {
            $firmId = $this->mapper->legacyId($firm['id']);

            if (! in_array($firmId, LegacyRecordMapper::IMPORTED_FIRM_IDS, true)) {
                continue;
            }

            $tenant = $this->firm($firm, $owner, $settingsSource, $report);
            $tenants[$firmId] = $tenant;
            $source = $settingsSource?->is($tenant) === true ? null : $settingsSource;

            $this->context->run($owner, $tenant->id, function () use ($firm, $firmId, $tenant, $source, $pairs, $objects, $legacyClients, $icDph, $owner, $report, $postalCodes, $recurringRows, $retiredRows, $lastNumbers, $invoiceRows): void {
                $this->settings->fillBlanks($tenant, $this->mapper->supplierProfile($firm), $source);

                $clientIds = $this->importClients($firmId, $pairs, $legacyClients, $icDph, $report);
                $this->importObjects($firmId, $objects, $clientIds, $postalCodes, $owner, $report);

                $lastNumber = $lastNumbers[(string) $firmId] ?? null;
                $recurringIds = $this->recurringInvoices->import($firmId, $tenant, $recurringRows, $retiredRows, is_string($lastNumber) ? $lastNumber : null, $report);

                $this->invoiceHistory->import($firmId, $tenant, $invoiceRows, $clientIds, $recurringIds, $report);
            });
        }

        return $tenants;
    }

    /** @param  array<string, mixed>  $firm */
    private function firm(array $firm, User $owner, ?Tenant $settingsSource, LegacyImportReport $report): Tenant
    {
        $firmId = $this->mapper->legacyId($firm['id']);
        $input = $this->mapper->firmInput($firm);
        $ico = $this->mapper->text($input['ico']);

        $existing = $ico === ''
            ? null
            : Tenant::query()->with('interface')->whereRaw("replace(ico, ' ', '') = ?", [$ico])->first();

        if ($existing === null) {
            $data = $this->validated(AddTenantData::class, $input, "firm #{$firmId}");
            $tenant = $this->registration->addTenant($owner, $data, $settingsSource);
            $report->add('firm', $firmId, $firmId, 'created', $tenant->name);

            return $tenant;
        }

        if (! $owner->hasActiveMembership($existing->id)) {
            throw new RuntimeException("firm #{$firmId}: the owner is not an active member of the existing firm with this IČO.");
        }

        $report->add('firm', $firmId, $firmId, 'existing', $existing->name);

        return $existing;
    }

    /**
     * @param  list<array{firm: int, client: int}>  $pairs
     * @param  list<array<string, mixed>>  $legacyClients
     * @param  array<int|string, mixed>  $icDph
     * @return array<int, string|null> legacy client id => client id, null when the client was deleted after an earlier import
     */
    private function importClients(int $firmId, array $pairs, array $legacyClients, array $icDph, LegacyImportReport $report): array
    {
        $byId = [];

        foreach ($legacyClients as $legacy) {
            $byId[$this->mapper->legacyId($legacy['id'])] = $legacy;
        }

        $clientIds = [];

        foreach ($pairs as $pair) {
            if ($pair['firm'] !== $firmId) {
                continue;
            }

            $legacy = $byId[$pair['client']] ?? throw new RuntimeException("client #{$pair['client']}: not found in the legacy file.");
            $input = $this->mapper->clientInput($legacy, $icDph);
            $name = $this->mapper->text($input['name']);
            $existing = $this->findClient($input);

            if ($existing !== null) {
                $clientIds[$pair['client']] = $existing->trashed() ? null : $existing->id;
                $report->add('client', $pair['client'], $firmId, 'existing', $name);

                continue;
            }

            $client = $this->clients->create($this->validated(ClientUpsertData::class, $input, "client #{$pair['client']}"));
            $clientIds[$pair['client']] = $client->id;
            $report->add('client', $pair['client'], $firmId, 'created', $name);
        }

        return $clientIds;
    }

    /** @param  array<string, mixed>  $input */
    private function findClient(array $input): ?Client
    {
        $ico = $input['ico'];

        if (is_string($ico)) {
            $byIco = Client::withTrashed()->where('ico', $ico)->first();

            if ($byIco !== null) {
                return $byIco;
            }
        }

        return Client::withTrashed()->whereRaw('lower(trim(name)) = lower(trim(?))', [$this->mapper->text($input['name'])])->first();
    }

    /**
     * @param  list<array{template_id: int, merged_template_ids: list<int>, firm: int, client: int, name: string, type: ObjectTypeEnum, street: ?string, city: string, billing_name: ?string}>  $objects
     * @param  array<int, string|null>  $clientIds
     * @param  array<string, string>  $postalCodes
     */
    private function importObjects(int $firmId, array $objects, array $clientIds, array $postalCodes, User $owner, LegacyImportReport $report): void
    {
        foreach ($objects as $object) {
            if ($object['firm'] !== $firmId) {
                continue;
            }

            $clientId = $clientIds[$object['client']] ?? null;
            $mappedPostalCode = $this->mappedPostalCode($object['name'], $postalCodes);
            $warnings = array_values(array_filter([
                $object['billing_name'] === null ? 'no billing name' : null,
                $mappedPostalCode === null ? 'no postal code' : null,
            ]));

            if ($clientId === null) {
                $report->add('object', $object['template_id'], $firmId, 'skipped', $object['name'], ['client deleted']);

                continue;
            }

            $existing = CleaningObject::withTrashed()
                ->with('client')
                ->whereRaw('lower(trim(name)) = lower(trim(?))', [$object['name']])
                ->first();

            if ($existing !== null) {
                $report->add('object', $object['template_id'], $firmId, 'existing', $object['name']);
                $this->correctPostalCode($existing, $object['template_id'], $firmId, $postalCodes, $report);
            } else {
                $data = $this->validated(ObjectUpsertData::class, [
                    'client_id' => $clientId,
                    'type' => $object['type']->value,
                    'name' => $object['name'],
                    'street' => $object['street'],
                    'city' => $object['city'],
                    'postal_code' => $mappedPostalCode,
                    'country' => 'SK',
                    'access_code' => null,
                    'key_box_code' => null,
                    'key_count' => null,
                    'special_instructions' => null,
                    'area_sqm' => null,
                    'floor' => null,
                    'is_active' => true,
                    'contacts' => [],
                    'billing_name' => $object['billing_name'],
                ], "template #{$object['template_id']}");

                $created = $this->objects->create($data, $owner);
                $report->add('object', $object['template_id'], $firmId, 'created', $object['name'], $warnings);

                if ($created->postal_code !== null) {
                    $report->add('postal_code', $object['template_id'], $firmId, 'filled', $object['name']);
                }
            }

            foreach ($object['merged_template_ids'] as $mergedId) {
                $report->add('object', $mergedId, $firmId, 'merged', $object['name']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string> lower-cased trimmed object name => raw postal code
     */
    private function postalCodeMap(array $payload): array
    {
        $raw = $payload['object_postal_codes'] ?? null;
        $map = [];

        if (! is_array($raw)) {
            return $map;
        }

        foreach ($raw as $name => $code) {
            if (is_string($code)) {
                $map[mb_strtolower(trim((string) $name))] = $code;
            }
        }

        return $map;
    }

    /** @param  array<string, string>  $postalCodes */
    private function mappedPostalCode(string $name, array $postalCodes): ?string
    {
        $raw = $postalCodes[mb_strtolower(trim($name))] ?? null;

        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\s+/u', '', $raw);

        return $digits !== null && preg_match('/^(\d{3})(\d{2})$/', $digits, $match) === 1 ? $match[1].' '.$match[2] : null;
    }

    /** @param  array<string, string>  $postalCodes */
    private function correctPostalCode(CleaningObject $object, int $templateId, int $firmId, array $postalCodes, LegacyImportReport $report): void
    {
        if (! isset($postalCodes[mb_strtolower(trim($object->name))])) {
            return;
        }

        $code = $this->mappedPostalCode($object->name, $postalCodes);

        if ($code === null) {
            $report->add('postal_code', $templateId, $firmId, 'invalid', $object->name);

            return;
        }

        $current = $this->digits($object->postal_code);

        if ($current === $this->digits($code)) {
            return;
        }

        if ($current === '') {
            $action = 'filled';
        } elseif ($current === $this->digits($object->client?->postal_code)) {
            $action = 'corrected';
        } else {
            return;
        }

        $this->objects->updatePostalCode($object, $code);
        $report->add('postal_code', $templateId, $firmId, $action, $object->name);
    }

    private function digits(?string $value): string
    {
        return (string) preg_replace('/\D/', '', (string) $value);
    }

    /** @param  array<int, Tenant>  $tenants */
    private function copyMedia(User $owner, ?Tenant $source, array $tenants, LegacyImportReport $report): void
    {
        if ($source === null) {
            return;
        }

        foreach ($tenants as $firmId => $tenant) {
            if (! $this->needsMedia($source, $tenant)) {
                continue;
            }

            try {
                $this->context->run($owner, $tenant->id, fn () => $this->settings->copyMissingMedia($source, $tenant));
                $report->add('media', $firmId, $firmId, 'copied', $tenant->name);
            } catch (Throwable $e) {
                report($e);
                $report->add('media', $firmId, $firmId, 'failed', $tenant->name);
            }
        }
    }

    /** @param  array<int, Tenant>  $tenants */
    private function reportMedia(?Tenant $source, array $tenants, LegacyImportReport $report, string $action): void
    {
        if ($source === null) {
            return;
        }

        foreach ($tenants as $firmId => $tenant) {
            if ($this->needsMedia($source, $tenant)) {
                $report->add('media', $firmId, $firmId, $action, $tenant->name);
            }
        }
    }

    private function needsMedia(Tenant $source, Tenant $target): bool
    {
        if ($source->is($target)) {
            return false;
        }

        return $source->signature_media_id !== null && $target->signature_media_id === null;
    }

    /**
     * @template T of Data
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $input
     * @return T
     */
    private function validated(string $class, array $input, string $reference): Data
    {
        try {
            return $class::validateAndCreate($input);
        } catch (ValidationException $e) {
            throw new RuntimeException($reference.': '.implode(' ', $e->validator->errors()->all()), previous: $e);
        }
    }
}
