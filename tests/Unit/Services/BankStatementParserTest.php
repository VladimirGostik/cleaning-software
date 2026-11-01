<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\Expenses\BankCsvMappingData;
use App\Services\Expenses\BankStatementParser;
use App\Services\Expenses\StatementRow;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BankStatementParserTest extends TestCase
{
    private BankStatementParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new BankStatementParser;
    }

    private function mapping(string $decimal = ',', string $format = 'd.m.Y'): BankCsvMappingData
    {
        return new BankCsvMappingData(';', 'Date', $format, 'Amount', 'Name', 'Note', 'Ref', $decimal);
    }

    /** @return array<string, array{string, string, string}> */
    public static function delimiters(): array
    {
        return [
            'semicolon' => ["Date;Amount;Name\r\n01.10.2026;-1,00;A", ';', 'Date'],
            'comma' => ["Date,Amount,Name\n01.10.2026,-1.00,A", ',', 'Date'],
            'tab' => ["Date\tAmount\tName\n01.10.2026\t-1,00\tA", "\t", 'Date'],
            'pipe' => ["Date|Amount|Name\n01.10.2026|-1,00|A", '|', 'Date'],
            'utf8 bom' => ["\xEF\xBB\xBFDate;Amount;Name\n01.10.2026;-1,00;A", ';', 'Date'],
        ];
    }

    #[DataProvider('delimiters')]
    public function test_delimiter_and_bom_are_detected(string $raw, string $delimiter, string $firstHeader): void
    {
        $table = $this->parser->parse($raw);

        $this->assertSame($delimiter, $table->delimiter);
        $this->assertSame($firstHeader, $table->header[0]);
        $this->assertCount(1, $table->records);
    }

    public function test_utf16_and_windows_1250_are_decoded(): void
    {
        $text = "Date;Amount;Name\n01.10.2026;-1,00;Čistiace prostriedky";

        $utf16 = $this->parser->parse("\xFF\xFE".mb_convert_encoding($text, 'UTF-16LE', 'UTF-8'));
        $cp1250 = $this->parser->parse((string) iconv('UTF-8', 'Windows-1250', $text));

        $this->assertSame('Čistiace prostriedky', $utf16->records[0][2]);
        $this->assertSame('Čistiace prostriedky', $cp1250->records[0][2]);
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function amounts(): array
    {
        return [
            'comma decimal' => [',', '-45,50', '45.50'],
            'thousands space' => [',', '-1 234,56', '1234.56'],
            'nbsp and currency' => [',', "-1\u{00A0}234,56 EUR", '1234.56'],
            'dot thousands' => [',', '-1.234,56', '1234.56'],
            'dot decimal' => ['.', '-1,234.56', '1234.56'],
            'integer' => ['.', '-12', '12.00'],
            'one decimal' => ['.', '-12.5', '12.50'],
            'plus sign is credit' => [',', '+45,50', null],
            'no sign is credit' => [',', '45,50', null],
            'zero is not a debit' => [',', '-0,00', null],
            'trailing minus' => [',', '12,50-', '12.50'],
            'unicode minus' => [',', "\u{2212}12,50", '12.50'],
            'parentheses' => [',', '(12,50)', '12.50'],
            'dot grouping needs three digits' => [',', '-1.234', '1234.00'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amounts_are_normalised_and_only_debits_are_kept(string $decimal, string $cell, ?string $expected): void
    {
        $table = $this->parser->parse("Date;Amount;Name;Note;Ref\n01.10.2026;{$cell};A;;");

        $extraction = $this->parser->extract($table, $this->mapping($decimal));

        if ($expected === null) {
            $this->assertSame([], $extraction->debitRows);
            $this->assertSame(1, $extraction->skippedCreditRows);

            return;
        }

        $this->assertSame($expected, $extraction->debitRows[0]->amount);
    }

    public function test_unparseable_amount_or_date_names_the_row(): void
    {
        $table = $this->parser->parse("Date;Amount;Name;Note;Ref\n01.10.2026;-1,00;A;;\n01.10.2026;abc;B;;");

        try {
            $this->parser->extract($table, $this->mapping());
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame([__('app.csv_row_invalid', ['row' => 3])], $e->errors()['file']);
        }
    }

    public function test_date_formats_and_trailing_time_parts(): void
    {
        $iso = $this->parser->extract(
            $this->parser->parse("Date;Amount;Name;Note;Ref\n2026-10-01 14:30:00;-1,00;A;;"),
            $this->mapping(',', 'Y-m-d'),
        );
        $slash = $this->parser->extract(
            $this->parser->parse("Date;Amount;Name;Note;Ref\n1/10/2026;-1,00;A;;"),
            $this->mapping(',', 'j/n/Y'),
        );

        $this->assertSame('2026-10-01', $iso->debitRows[0]->date);
        $this->assertSame('2026-10-01', $slash->debitRows[0]->date);
    }

    public function test_quoted_multiline_fields_and_blank_lines(): void
    {
        $table = $this->parser->parse("Date;Amount;Name;Note;Ref\n\n01.10.2026;-1,00;\"Firma; s.r.o.\";\"line1\nline2\";9\n\n");

        $row = $this->parser->extract($table, $this->mapping())->debitRows[0];

        $this->assertSame('Firma; s.r.o.', $row->counterparty);
        $this->assertSame('line1 line2', $row->note);
    }

    public function test_mapping_fit_checks_every_named_column(): void
    {
        $table = $this->parser->parse("Date;Amount;Name\n01.10.2026;-1,00;A");

        $this->assertTrue($this->parser->fits($table, new BankCsvMappingData(';', 'Date', 'd.m.Y', 'Amount', 'Name', null, null, ',')));
        $this->assertFalse($this->parser->fits($table, $this->mapping()));
    }

    public function test_hashes_are_stable_normalised_and_ordinal_for_repeats(): void
    {
        $a = new StatementRow(2, '2026-10-01', '3.50', 'Kaviareň  X', 'Káva', '1');
        $same = new StatementRow(9, '2026-10-01', '3.50', ' kaviareň x ', 'KÁVA', '1');
        $other = new StatementRow(3, '2026-10-02', '3.50', 'Kaviareň X', 'Káva', '1');

        $first = $this->parser->hashes([$a, $same, $other]);
        $second = $this->parser->hashes([$same, $a, $other]);

        $this->assertCount(3, array_unique($first));
        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first[0]));
    }

    public function test_clean_text_strips_formula_and_control_characters_and_truncates(): void
    {
        $this->assertSame('cmd|x', $this->parser->cleanText('=cmd|x', 50));
        $this->assertSame('SUM(1)', $this->parser->cleanText('+@-  SUM(1)', 50));
        $this->assertSame('a b', $this->parser->cleanText("a\x00\t\r\nb", 50));
        $this->assertNull($this->parser->cleanText('=+-@', 50));
        $this->assertNull($this->parser->cleanText(null, 50));
        $this->assertSame('ččč', $this->parser->cleanText('čččččč', 3));
    }

    /** @return array<string, array{string, string}> */
    public static function mismatchedSeparators(): array
    {
        return [
            'dot decimal under comma setting' => [',', '-12.50'],
            'comma decimal under dot setting' => ['.', '-12,50'],
            'three decimals' => [',', '-12,505'],
            'misplaced group' => ['.', '-12,34,567'],
        ];
    }

    #[DataProvider('mismatchedSeparators')]
    public function test_separator_mismatch_is_rejected_instead_of_misread(string $decimal, string $cell): void
    {
        $table = $this->parser->parse("Date;Amount;Name;Note;Ref\n01.10.2026;{$cell};A;;");

        $this->expectException(ValidationException::class);

        $this->parser->extract($table, $this->mapping($decimal));
    }

    public function test_separate_debit_and_credit_columns(): void
    {
        $table = $this->parser->parse("Date;Debet;Kredit;Name\n01.10.2026;12,50;;Shell\n02.10.2026;;300,00;Klient\n03.10.2026;0,00;;Nula\n04.10.2026;1 000,00;;Metro");
        $mapping = new BankCsvMappingData(';', 'Date', 'd.m.Y', null, 'Name', null, null, ',', 'Debet', 'Kredit');

        $extraction = $this->parser->extract($table, $mapping);

        $this->assertSame(['12.50', '1000.00'], array_map(fn (StatementRow $row): string => $row->amount, $extraction->debitRows));
        $this->assertSame(2, $extraction->skippedCreditRows);
    }

    public function test_mapping_without_date_or_amount_columns_does_not_fit(): void
    {
        $table = $this->parser->parse("Date;Amount;Name\n01.10.2026;-1,00;A");

        $this->assertFalse($this->parser->fits($table, new BankCsvMappingData(';', '', 'd.m.Y', 'Amount', null, null, null, ',')));
        $this->assertFalse($this->parser->fits($table, new BankCsvMappingData(';', 'Date', 'd.m.Y', null, null, null, null, ',')));
        $this->assertFalse($this->parser->fits($table, new BankCsvMappingData(';', 'Date', 'd.m.Y', '', null, null, null, ',')));
        $this->assertTrue($this->parser->fits($table, new BankCsvMappingData(';', 'Date', 'd.m.Y', null, null, null, null, ',', 'Amount')));
    }

    public function test_metadata_lines_before_the_header_are_skipped(): void
    {
        $raw = "Account;SK89 7500 0000 0001 2345 6789\nPeriod;01.10.2026 - 31.10.2026\n\nDate;Amount;Name\n01.10.2026;-1,00;A\n02.10.2026;-2,00;B";

        $table = $this->parser->parse($raw);

        $this->assertSame(['Date', 'Amount', 'Name'], $table->header);
        $this->assertCount(2, $table->records);
    }

    public function test_manual_text_keeps_leading_symbols(): void
    {
        $this->assertSame('@Home s.r.o.', $this->parser->cleanText('@Home s.r.o.', 50, false));
    }
}
