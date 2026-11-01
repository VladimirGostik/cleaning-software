<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Support\SafeText;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CamtStatementParser
{
    private const string NAMESPACE_URI = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02';

    public function parse(string $raw): CamtStatement
    {
        if (trim($raw) === '') {
            throw $this->invalid('expense_import_file_invalid');
        }

        $xpath = new DOMXPath($this->load($raw));
        $xpath->registerNamespace('c', self::NAMESPACE_URI);

        $statements = $this->elements($xpath, '/c:Document/c:BkToCstmrStmt/c:Stmt');

        if ($statements === []) {
            throw $this->invalid('expense_import_file_invalid');
        }

        $iban = '';
        $rows = [];
        $references = [];
        $credits = 0;
        $others = 0;
        $max = config()->integer('expenses.statement.max_rows');

        foreach ($statements as $statement) {
            $current = self::normalizeIban($this->text($xpath, 'c:Acct/c:Id/c:IBAN', $statement) ?? '');

            if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $current) !== 1 || ($iban !== '' && $iban !== $current)) {
                throw $this->invalid('expense_import_file_invalid');
            }

            $iban = $current;

            foreach ($this->elements($xpath, 'c:Ntry', $statement) as $entry) {
                if ($this->text($xpath, 'c:Sts', $entry) !== 'BOOK' || strtolower($this->text($xpath, 'c:RvslInd', $entry) ?? '') === 'true') {
                    $others++;

                    continue;
                }

                $direction = $this->text($xpath, 'c:CdtDbtInd', $entry);

                if ($direction === 'CRDT') {
                    $credits++;

                    continue;
                }

                if ($direction !== 'DBIT') {
                    throw $this->invalid('expense_import_file_invalid');
                }

                if ($this->isZeroAmount($xpath, $entry)) {
                    $others++;

                    continue;
                }

                $row = $this->debit($xpath, $entry, count($rows) + 1);

                if (isset($references[$row->reference])) {
                    throw $this->invalid('expense_import_duplicate_reference');
                }

                $references[$row->reference] = true;
                $rows[] = $row;

                if (count($rows) > $max) {
                    throw $this->invalid('expense_import_too_many_rows', ['max' => $max]);
                }
            }
        }

        return new CamtStatement($iban, $rows, $credits, $others);
    }

    public function entryKey(string $iban, string $reference): string
    {
        return hash('sha256', 'camt053|'.self::normalizeIban($iban).'|'.$reference);
    }

    public static function normalizeIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', '', $iban));
    }

    private function load(string $raw): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($raw, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->doctype !== null || ! $document->documentElement instanceof DOMElement) {
            throw $this->invalid('expense_import_file_invalid');
        }

        $root = $document->documentElement;

        if ($root->localName !== 'Document' || $root->namespaceURI !== self::NAMESPACE_URI) {
            throw $this->invalid('expense_import_unsupported_format');
        }

        return $document;
    }

    private function isZeroAmount(DOMXPath $xpath, DOMElement $entry): bool
    {
        return preg_match('/^0+(\.0{1,2})?$/', $this->text($xpath, 'c:Amt', $entry) ?? '') === 1;
    }

    private function debit(DOMXPath $xpath, DOMElement $entry, int $position): StatementRow
    {
        $reference = Str::trim($this->text($xpath, 'c:NtryRef', $entry) ?? '');

        if ($reference === '') {
            throw $this->invalid('expense_import_missing_reference');
        }

        if (mb_strlen($reference) > 100) {
            throw $this->invalid('expense_import_file_invalid');
        }

        $amount = $this->elements($xpath, 'c:Amt', $entry)[0] ?? null;

        if ($amount === null || $amount->getAttribute('Ccy') !== 'EUR') {
            throw $this->invalid('expense_import_unsupported_currency');
        }

        $value = trim($amount->textContent);

        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) !== 1 || (float) $value <= 0.0) {
            throw $this->invalid('expense_import_file_invalid');
        }

        $details = $this->elements($xpath, 'c:NtryDtls/c:TxDtls', $entry);
        $single = count($details) === 1 ? $details[0] : null;

        return new StatementRow(
            row: $position,
            date: $this->bookingDate($xpath, $entry),
            amount: number_format((float) $value, 2, '.', ''),
            counterparty: $single === null ? null : SafeText::clean($this->text($xpath, 'c:RltdPties/c:Cdtr/c:Nm', $single), 200),
            note: $single === null ? null : SafeText::clean($this->note($xpath, $single), 500),
            reference: $reference,
        );
    }

    private function note(DOMXPath $xpath, DOMElement $details): ?string
    {
        $parts = array_map(
            static fn (DOMElement $line): string => trim($line->textContent),
            $this->elements($xpath, 'c:RmtInf/c:Ustrd', $details),
        );

        $joined = trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));

        return $joined !== '' ? $joined : $this->text($xpath, 'c:AddtlTxInf', $details);
    }

    private function bookingDate(DOMXPath $xpath, DOMElement $entry): string
    {
        $day = $this->text($xpath, 'c:BookgDt/c:Dt', $entry) ?? substr($this->text($xpath, 'c:BookgDt/c:DtTm', $entry) ?? '', 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $day) {
            throw $this->invalid('expense_import_file_invalid');
        }

        return $day;
    }

    private function text(DOMXPath $xpath, string $path, ?DOMElement $context = null): ?string
    {
        $node = $this->elements($xpath, $path, $context)[0] ?? null;
        $text = $node === null ? '' : trim($node->textContent);

        return $text === '' ? null : $text;
    }

    /** @return list<DOMElement> */
    private function elements(DOMXPath $xpath, string $path, ?DOMElement $context = null): array
    {
        $elements = [];

        foreach ($xpath->query($path, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /** @param  array<string, int|string>  $replace */
    private function invalid(string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['file' => [__('app.'.$key, $replace)]]);
    }
}
