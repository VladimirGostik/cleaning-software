<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Data\Expenses\BankCsvMappingData;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

final readonly class BankStatementParser
{
    private const array DELIMITERS = [';', ',', "\t", '|'];

    public function parse(string $raw): ParsedStatement
    {
        $text = $this->toUtf8($raw);

        $best = null;

        foreach (self::DELIMITERS as $candidate) {
            $rows = $this->readRows($text, $candidate);
            $counts = array_count_values(array_map('count', $rows));

            if ($counts === []) {
                continue;
            }

            arsort($counts);
            $modal = (int) array_key_first($counts);
            $score = $modal > 1 ? $counts[$modal] : 0;

            if ($best === null || $score > $best['score'] || ($score === $best['score'] && $modal > $best['modal'])) {
                $best = ['delimiter' => $candidate, 'rows' => $rows, 'modal' => $modal, 'score' => $score];
            }
        }

        if ($best === null || $best['score'] < 2) {
            throw $this->invalid('csv_invalid');
        }

        $rows = $best['rows'];
        $start = array_find_key($rows, static fn (array $row): bool => count($row) === $best['modal']);
        $table = array_slice($rows, (int) $start);
        $header = $table[0] ?? throw $this->invalid('csv_invalid');

        return new ParsedStatement($best['delimiter'], $header, array_slice($table, 1));
    }

    /** @return list<list<string>> */
    private function readRows(string $text, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw $this->invalid('csv_invalid');
        }

        fwrite($handle, $text);
        rewind($handle);

        $rows = [];

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $trimmed = array_map(static fn (?string $cell): string => trim((string) $cell), $cells);

            if (array_filter($trimmed, static fn (string $cell): bool => $cell !== '') !== []) {
                $rows[] = $trimmed;
            }
        }

        fclose($handle);

        return $rows;
    }

    public function fits(ParsedStatement $table, BankCsvMappingData $mapping): bool
    {
        $filled = static fn (?string $column): bool => $column !== null && $column !== '';

        if (! $filled($mapping->date_column) || ! ($filled($mapping->amount_column) || $filled($mapping->debit_column))) {
            return false;
        }

        $columns = array_filter([
            $mapping->date_column, $mapping->amount_column, $mapping->debit_column, $mapping->credit_column,
            $mapping->counterparty_column, $mapping->note_column, $mapping->reference_column,
        ], $filled);

        return array_all($columns, static fn (?string $column): bool => in_array($column, $table->header, true));
    }

    public function extract(ParsedStatement $table, BankCsvMappingData $mapping): StatementExtraction
    {
        $index = array_flip($table->header);
        $column = static fn (?string $name): ?int => $name === null || $name === '' ? null : ($index[$name] ?? null);

        $date = $column($mapping->date_column) ?? 0;
        $debit = $column($mapping->debit_column);
        $amount = $column($mapping->amount_column);
        $counterparty = $column($mapping->counterparty_column);
        $note = $column($mapping->note_column);
        $reference = $column($mapping->reference_column);

        $rows = [];
        $skipped = 0;

        foreach ($table->records as $offset => $cells) {
            $number = $offset + 2;
            $day = $this->parseDate($cells[$date] ?? '', $mapping->date_format);
            $cell = trim($cells[$debit ?? $amount ?? 0] ?? '');
            $signed = $this->signedAmount($cell, $mapping->decimal_separator);

            if ($day === null || ($signed === null && ! ($debit !== null && $cell === ''))) {
                throw ValidationException::withMessages(['file' => [__('app.csv_row_invalid', ['row' => $number])]]);
            }

            $isDebit = $debit !== null
                ? $signed !== null && (float) $signed !== 0.0
                : str_starts_with((string) $signed, '-');

            if (! $isDebit) {
                $skipped++;

                continue;
            }

            $rows[] = new StatementRow(
                row: $number,
                date: $day,
                amount: ltrim((string) $signed, '-'),
                counterparty: $this->cleanText($counterparty === null ? null : ($cells[$counterparty] ?? null), 200),
                note: $this->cleanText($note === null ? null : ($cells[$note] ?? null), 500),
                reference: $this->cleanText($reference === null ? null : ($cells[$reference] ?? null), 100),
            );
        }

        return new StatementExtraction($rows, $skipped);
    }

    /**
     * Repeated identical rows in one file get an occurrence suffix so genuine duplicates survive while a re-import still collides.
     *
     * @param  list<object{date: string, amount: string, counterparty: ?string, note: ?string, reference: ?string}>  $rows
     * @return list<string>
     */
    public function hashes(array $rows): array
    {
        $seen = [];
        $hashes = [];

        foreach ($rows as $row) {
            $key = implode('|', [
                $row->date,
                $row->amount,
                $this->normalise($row->counterparty),
                $this->normalise($row->note),
                $this->normalise($row->reference),
            ]);

            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $hashes[] = hash('sha256', $key.'#'.$seen[$key]);
        }

        return $hashes;
    }

    public function cleanText(?string $value, int $max, bool $neutraliseFormulas = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $collapsed = preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $value) ?? '';
        $safe = $neutraliseFormulas ? ltrim(trim($collapsed), '=+-@ ') : trim($collapsed);
        $limited = mb_substr($safe, 0, $max);

        return $limited === '' ? null : $limited;
    }

    private function normalise(?string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }

    private function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $converted = iconv('Windows-1250', 'UTF-8//IGNORE', $raw);

        if ($converted === false) {
            throw $this->invalid('csv_invalid');
        }

        return $converted;
    }

    /** Returns a signed `-12.50` / `12.50` string, or null when the cell is not a plain amount. */
    private function signedAmount(string $raw, string $decimalSeparator): ?string
    {
        $value = (string) preg_replace('/[^\d.,+\-()]/u', '', str_replace("\u{2212}", '-', preg_replace('/[\s\x{00A0}]+/u', '', $raw) ?? ''));

        $sign = '';

        if (preg_match('/^\((.*)\)$/', $value, $m) === 1) {
            $sign = '-';
            $value = $m[1];
        } elseif (preg_match('/^(.*\d)([+-])$/', $value, $m) === 1) {
            $sign = $m[2];
            $value = $m[1];
        } elseif (preg_match('/^([+-])(.*)$/', $value, $m) === 1) {
            $sign = $m[1];
            $value = $m[2];
        }

        $pattern = $decimalSeparator === ','
            ? '/^(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?$/'
            : '/^(\d{1,3}(?:,\d{3})+|\d+)(?:\.(\d{1,2}))?$/';

        if (strlen($value) > 20 || preg_match($pattern, $value, $parts) !== 1) {
            return null;
        }

        $digits = ltrim(str_replace(['.', ','], '', $parts[1]), '0');
        $normalised = ($digits === '' ? '0' : $digits).'.'.str_pad($parts[2] ?? '', 2, '0');

        return ($sign === '-' && (float) $normalised !== 0.0 ? '-' : '').$normalised;
    }

    private function parseDate(string $raw, string $format): ?string
    {
        $value = str_contains($format, ' ') || str_contains($format, ':') ? $raw : (explode(' ', $raw)[0]);
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function invalid(string $key): ValidationException
    {
        return ValidationException::withMessages(['file' => [__('app.'.$key)]]);
    }
}
