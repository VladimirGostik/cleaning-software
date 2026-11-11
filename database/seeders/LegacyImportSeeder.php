<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\LegacyImport\LegacyImportReport;
use App\Services\LegacyImport\LegacyImportService;
use App\Services\LegacyImport\LegacyRecordMapper;
use Illuminate\Database\Seeder;
use Illuminate\Encryption\Encrypter;
use RuntimeException;
use Throwable;

final class LegacyImportSeeder extends Seeder
{
    public function run(LegacyImportService $service, LegacyRecordMapper $mapper): void
    {
        $payload = $this->payload();
        $firms = $this->existingFirms($payload, $mapper);
        $source = $this->settingsSource($firms);
        $owner = $this->owner($source);

        auth()->setUser($owner);

        $report = $service->import($payload, $owner, $source, (bool) config('legacy_import.dry_run'));

        $this->printReport($report);

        if ($this->mediaFailures($report) > 0) {
            $this->command?->error('Media copy failed for some firms. The import itself is committed; re-run to finish the media.');

            throw new RuntimeException('Legacy import finished with media failures.');
        }
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $key = config('legacy_import.key');
        $raw = is_string($key) && $key !== '' ? base64_decode($key, true) : false;

        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('Legacy import key is missing or invalid.');
        }

        $path = config('legacy_import.path');

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('Legacy import file not found.');
        }

        try {
            $json = (new Encrypter($raw, 'aes-256-gcm'))->decryptString((string) file_get_contents($path));
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Legacy import file could not be decrypted or decoded.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Legacy import file is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<Tenant>
     */
    private function existingFirms(array $payload, LegacyRecordMapper $mapper): array
    {
        $firms = is_array($payload['firms'] ?? null) ? $payload['firms'] : [];
        $icos = [];

        foreach ($firms as $firm) {
            if (! is_array($firm) || ! in_array($mapper->legacyId($firm['id'] ?? null), LegacyRecordMapper::IMPORTED_FIRM_IDS, true)) {
                continue;
            }

            /** @var array<string, mixed> $firm */
            $ico = $mapper->firmInput($firm)['ico'];

            if (is_string($ico) && $ico !== '') {
                $icos[] = $ico;
            }
        }

        return array_values(Tenant::query()->whereIn('ico', $icos)->orderBy('created_at')->orderBy('id')->get()->all());
    }

    /** @param  list<Tenant>  $firms */
    private function settingsSource(array $firms): Tenant
    {
        if ($firms === []) {
            throw new RuntimeException('No imported firm exists yet; create the owner with app:create-owner first.');
        }

        foreach ($firms as $firm) {
            if ($firm->signature_media_id !== null) {
                return $firm;
            }
        }

        return $firms[0];
    }

    private function owner(Tenant $source): User
    {
        $owner = $source->owner;

        if ($owner === null || ! $owner->is_active || ! $owner->hasActiveMembership($source->id)) {
            throw new RuntimeException('The firm owner is missing, inactive, or not an active member of the firm.');
        }

        return $owner;
    }

    private function mediaFailures(LegacyImportReport $report): int
    {
        return count(array_filter($report->rows(), fn (array $row): bool => $row['entity'] === 'media' && $row['action'] === 'failed'));
    }

    private function printReport(LegacyImportReport $report): void
    {
        $count = fn (string $entity, string $action): int => $report->count($entity, $action);

        $this->command?->info(sprintf(
            'Firms: %d created, %d existing. Clients: %d created, %d existing. Objects: %d created, %d existing, %d merged, %d skipped. Recurring invoices: %d created, %d existing, %d skipped, %d retired. Drafts: %d created, %d removed. Postal codes: %d filled, %d corrected, %d invalid. Firms with auto-issue default: %d. Invoices: %d created, %d existing, %d skipped, %d payments synced, %d buyers synced. Media failures: %d.',
            $count('firm', 'created'), $count('firm', 'existing'),
            $count('client', 'created'), $count('client', 'existing'),
            $count('object', 'created'), $count('object', 'existing'), $count('object', 'merged'), $count('object', 'skipped'),
            $count('recurring', 'created'), $count('recurring', 'existing'), $count('recurring', 'skipped'), $count('recurring', 'retired'), $count('draft', 'created'), $count('draft', 'removed'),
            $count('postal_code', 'filled'), $count('postal_code', 'corrected'), $count('postal_code', 'invalid'),
            $count('auto_issue_firm', 'issued_default'),
            $count('invoice', 'created'), $count('invoice', 'existing'), $count('invoice', 'skipped'), $count('invoice', 'payment_synced'), $count('invoice', 'buyer_synced'),
            $this->mediaFailures($report),
        ));

        if ($report->dryRun) {
            $this->command?->warn('DRY RUN, nothing written.');
        }
    }
}
