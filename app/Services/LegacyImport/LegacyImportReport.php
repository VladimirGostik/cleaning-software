<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

final class LegacyImportReport
{
    public bool $dryRun = false;

    /** @var list<array{entity: string, legacy_id: int, firm: int, action: string, name: string, warnings: list<string>}> */
    private array $rows = [];

    /** @param  list<string>  $warnings */
    public function add(string $entity, int $legacyId, int $firm, string $action, string $name, array $warnings = []): void
    {
        $this->rows[] = [
            'entity' => $entity,
            'legacy_id' => $legacyId,
            'firm' => $firm,
            'action' => $action,
            'name' => $name,
            'warnings' => $warnings,
        ];
    }

    /** @return list<array{entity: string, legacy_id: int, firm: int, action: string, name: string, warnings: list<string>}> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function count(string $entity, string $action, ?int $firm = null): int
    {
        return count(array_filter(
            $this->rows,
            fn (array $row): bool => $row['entity'] === $entity
                && $row['action'] === $action
                && ($firm === null || $row['firm'] === $firm),
        ));
    }

    /** @return list<int> */
    public function firms(): array
    {
        return array_values(array_unique(array_column($this->rows, 'firm')));
    }
}
