<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\LegacyImport\LegacyImportReport;
use App\Services\LegacyImport\LegacyImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

#[Signature('app:import-legacy {--owner=} {--disk=local} {--path=import/legacy-cleaning-system.json} {--copy-settings-from=} {--dry-run} {--details}')]
#[Description('Import the legacy firms, clients and objects from a JSON file')]
final class ImportLegacyData extends Command
{
    public function handle(LegacyImportService $service): int
    {
        $owner = User::query()->where('email', $this->textOption('owner'))->where('is_active', true)->first();

        if ($owner === null) {
            $this->error('Owner not found or inactive.');

            return self::FAILURE;
        }

        $payload = $this->payload();

        if ($payload === null) {
            return self::FAILURE;
        }

        $source = null;
        $sourceOption = $this->option('copy-settings-from');

        if (is_string($sourceOption) && $sourceOption !== '') {
            $source = $this->settingsSource($sourceOption);

            if ($source === null || ! $owner->hasActiveMembership($source->id)) {
                $this->error('Settings source firm not found, or the owner is not an active member of it.');

                return self::FAILURE;
            }
        }

        auth()->setUser($owner);

        try {
            $report = $service->import($payload, $owner, $source, (bool) $this->option('dry-run'));
        } catch (QueryException $e) {
            $this->error("Database error (SQLSTATE {$e->getCode()}). Nothing was written.");

            return self::FAILURE;
        } catch (RuntimeException|AuthorizationException $e) {
            $this->error($e->getMessage());
            $this->error('Nothing was written.');

            return self::FAILURE;
        }

        $this->printReport($report);

        return $this->reportMediaFailures($report) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, mixed>|null */
    private function payload(): ?array
    {
        $disk = Storage::disk($this->textOption('disk'));
        $path = $this->textOption('path');

        if (! $disk->exists($path)) {
            $this->error('Import file not found.');

            return null;
        }

        try {
            $decoded = json_decode((string) $disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->error('Import file is not valid JSON.');

            return null;
        }

        if (! is_array($decoded)) {
            $this->error('Import file is not a JSON object.');

            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function textOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    private function settingsSource(string $identifier): ?Tenant
    {
        $query = Tenant::query()->with('interface');

        return Str::isUuid($identifier)
            ? $query->find($identifier)
            : $query->whereRaw("replace(ico, ' ', '') = ?", [preg_replace('/\s+/', '', $identifier)])->first();
    }

    private function reportMediaFailures(LegacyImportReport $report): bool
    {
        $failed = array_filter($report->rows(), fn (array $row): bool => $row['entity'] === 'media' && $row['action'] === 'failed');

        foreach ($failed as $row) {
            $this->error("Media copy failed (firm #{$row['firm']}). The import itself is committed; re-run to finish the media.");
        }

        return $failed !== [];
    }

    private function printReport(LegacyImportReport $report): void
    {
        $perFirm = array_map(fn (int $firm): array => [
            $firm,
            $report->count('firm', 'created', $firm),
            $report->count('firm', 'existing', $firm),
            $report->count('client', 'created', $firm),
            $report->count('client', 'existing', $firm),
            $report->count('object', 'created', $firm),
            $report->count('object', 'existing', $firm),
            $report->count('object', 'merged', $firm),
            $report->count('object', 'skipped', $firm),
            $report->count('media', 'copied', $firm) + $report->count('media', 'would_copy', $firm),
            $report->count('recurring', 'created', $firm),
            $report->count('recurring', 'existing', $firm),
            $report->count('recurring', 'skipped', $firm),
            $report->count('draft', 'created', $firm),
            $report->count('draft', 'removed', $firm),
            $report->count('recurring', 'retired', $firm),
            $report->count('postal_code', 'corrected', $firm),
            $report->count('numbering', 'raised', $firm),
            $report->count('number_format', 'set', $firm).'/'.$report->count('number_format', 'kept', $firm),
        ], $report->firms());

        $this->table(
            ['Firm #', 'Firm new', 'Firm existing', 'Clients new', 'Clients existing', 'Objects new', 'Objects existing', 'Merged', 'Skipped', 'Media', 'Recurring new', 'Recurring existing', 'Recurring skipped', 'Drafts new', 'Drafts removed', 'Recurring retired', 'Postal corrected', 'Numbering raised', 'Format set/kept'],
            $perFirm,
        );

        $this->info(sprintf(
            'Created: %d firms, %d clients, %d objects, %d recurring invoices, %d drafts. Removed drafts already billed in the legacy app: %d. Retired recurring invoices: %d. Postal codes filled: %d, corrected: %d, skipped invalid: %d.',
            $report->count('firm', 'created'),
            $report->count('client', 'created'),
            $report->count('object', 'created'),
            $report->count('recurring', 'created'),
            $report->count('draft', 'created'),
            $report->count('draft', 'removed'),
            $report->count('recurring', 'retired'),
            $report->count('postal_code', 'filled'),
            $report->count('postal_code', 'corrected'),
            $report->count('postal_code', 'invalid'),
        ));

        $issuedFirms = $report->count('auto_issue_firm', 'issued_default');

        if ($issuedFirms > 0) {
            $this->warn(sprintf('%d imported firm(s) have recurring_default_state = issued: generated recurring invoices will be auto-issued.', $issuedFirms));
        }

        $warnings = [];

        foreach ($report->rows() as $row) {
            foreach ($row['warnings'] as $warning) {
                $warnings[$warning][] = "{$row['entity']} #{$row['legacy_id']} (firm #{$row['firm']})";
            }
        }

        foreach ($warnings as $warning => $references) {
            $this->warn(sprintf('%d x %s', count($references), $warning));
        }

        if ($this->option('details')) {
            foreach ($warnings as $warning => $references) {
                $this->line($warning.': '.implode(', ', $references));
            }

            $this->table(
                ['Entity', 'Legacy #', 'Firm #', 'Action', 'Name'],
                array_map(fn (array $row): array => [$row['entity'], $row['legacy_id'], $row['firm'], $row['action'], $row['name']], $report->rows()),
            );
        }

        if ($report->dryRun) {
            $this->warn('DRY RUN, nothing written.');
        }
    }
}
