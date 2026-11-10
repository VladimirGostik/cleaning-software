<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Expenses\CamtStatementParser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesCamtStatements;
use Tests\TestCase;

final class CamtStatementParserTest extends TestCase
{
    use CreatesCamtStatements;

    private CamtStatementParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CamtStatementParser;
    }

    private function assertRefused(string $xml, ?string $key = null): void
    {
        try {
            $this->parser->parse($xml);
            $this->fail('Expected the file to be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());

            if ($key !== null) {
                $this->assertSame(__('app.'.$key, ['max' => 2000]), $e->validator->errors()->first('file'));
            }
        }
    }

    public function test_debits_are_parsed_and_other_entries_are_counted(): void
    {
        $statement = $this->parser->parse($this->camtXml([
            $this->debit('A1', '45.5', '2026-10-01'),
            $this->credit('A2'),
            $this->debit('A3', '7.00', '2026-10-02', ['reversal' => 'true']),
            $this->debit('A4', '8.00', '2026-10-02', ['status' => 'PDNG']),
            $this->credit('A5', '3.00', '2026-10-03', ['status' => 'PDNG']),
            $this->debit('A6', '1234.56', '2026-10-04'),
        ]));

        $this->assertSame(self::STATEMENT_IBAN, $statement->iban);
        $this->assertSame(1, $statement->skippedCreditRows);
        $this->assertSame(3, $statement->skippedOtherRows);
        $this->assertCount(2, $statement->debitRows);
        $this->assertSame('45.50', $statement->debitRows[0]->amount);
        $this->assertSame('A1', $statement->debitRows[0]->reference);
        $this->assertSame('2026-10-01', $statement->debitRows[0]->date);
        $this->assertSame('Dodavatel A1', $statement->debitRows[0]->counterparty);
        $this->assertSame('Platba A1', $statement->debitRows[0]->note);
        $this->assertSame(1, $statement->debitRows[0]->row);
        $this->assertSame(2, $statement->debitRows[1]->row);
        $this->assertSame('1234.56', $statement->debitRows[1]->amount);
    }

    public function test_a_zero_amount_debit_is_skipped_and_counted_as_other(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->debit('A0', '0.00'), $this->debit('A1', '0'), $this->debit('A2', '5.00')]));

        $this->assertCount(1, $statement->debitRows);
        $this->assertSame('A2', $statement->debitRows[0]->reference);
        $this->assertSame(2, $statement->skippedOtherRows);
    }

    public function test_a_zero_amount_debit_still_needs_valid_markup_but_not_a_reference(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->debit('', '0.00', '2026-10-01', ['reference' => null])]));

        $this->assertSame([], $statement->debitRows);
        $this->assertSame(1, $statement->skippedOtherRows);
        $this->assertRefused($this->camtXml([$this->debit('A1', '-0.00')]), 'expense_import_file_invalid');
    }

    public function test_reference_is_trimmed_like_the_request_middleware_does(): void
    {
        $nbsp = "\u{00A0}";
        $statement = $this->parser->parse($this->camtXml([$this->debit($nbsp."\u{200B}\u{FEFF} A1 ".$nbsp)]));

        $this->assertSame('A1', $statement->debitRows[0]->reference);
        $this->assertSame(Str::trim($nbsp.'A1'.$nbsp), $statement->debitRows[0]->reference);
    }

    public function test_normalize_iban_drops_every_kind_of_padding(): void
    {
        $this->assertSame(self::STATEMENT_IBAN, CamtStatementParser::normalizeIban("\u{00A0}sk11 1100\u{00A0}0000 0011 1111 1111\u{200B}\u{FEFF}"));
    }

    public function test_iban_is_normalised(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->debit('A1')], 'sk11 1100 0000 0011 1111 1111'));

        $this->assertSame(self::STATEMENT_IBAN, $statement->iban);
    }

    public function test_reference_is_trimmed_and_kept_verbatim(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->debit('  =Ab 01  ')]));

        $this->assertSame('=Ab 01', $statement->debitRows[0]->reference);
    }

    public function test_booking_date_falls_back_to_the_date_part_of_the_timestamp(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->debit('A1', '5.00', '2026-10-01', ['datetime' => '2026-10-05T23:30:00+02:00'])]));

        $this->assertSame('2026-10-05', $statement->debitRows[0]->date);
    }

    public function test_counterparty_and_note_are_cleaned_and_dropped_for_split_entries(): void
    {
        $statement = $this->parser->parse($this->camtXml([
            $this->debit('A1', '5.00', '2026-10-01', ['counterparty' => "=cmd|x\n", 'note' => '+SUM(1)']),
            $this->debit('A2', '5.00', '2026-10-01', ['tx_details' => 2]),
            $this->debit('A3', '5.00', '2026-10-01', ['counterparty' => null, 'note' => null]),
        ]));

        $this->assertSame('cmd|x', $statement->debitRows[0]->counterparty);
        $this->assertSame('SUM(1)', $statement->debitRows[0]->note);
        $this->assertNull($statement->debitRows[1]->counterparty);
        $this->assertNull($statement->debitRows[1]->note);
        $this->assertNull($statement->debitRows[2]->counterparty);
        $this->assertNull($statement->debitRows[2]->note);
    }

    public function test_additional_info_is_the_note_when_there_is_no_unstructured_text(): void
    {
        $xml = str_replace('<RmtInf><Ustrd>Platba A1</Ustrd></RmtInf>', '<AddtlTxInf>Poplatok</AddtlTxInf>', $this->camtXml([$this->debit('A1')]));

        $this->assertSame('Poplatok', $this->parser->parse($xml)->debitRows[0]->note);
    }

    public function test_a_file_with_no_debits_is_valid(): void
    {
        $statement = $this->parser->parse($this->camtXml([$this->credit('A1')]));

        $this->assertSame([], $statement->debitRows);
        $this->assertSame(1, $statement->skippedCreditRows);
    }

    public function test_entry_key_depends_on_the_iban_and_the_reference(): void
    {
        $this->assertNotSame(
            $this->parser->entryKey(self::STATEMENT_IBAN, 'R1'),
            $this->parser->entryKey(self::OTHER_IBAN, 'R1'),
        );
        $this->assertNotSame(
            $this->parser->entryKey(self::STATEMENT_IBAN, 'R1'),
            $this->parser->entryKey(self::STATEMENT_IBAN, 'R2'),
        );
        $this->assertSame(
            $this->parser->entryKey(self::STATEMENT_IBAN, 'R1'),
            $this->parser->entryKey('sk11 1100 0000 0011 1111 1111', 'R1'),
        );
        $this->assertSame(hash('sha256', 'camt053|'.self::STATEMENT_IBAN.'|R1'), $this->parser->entryKey(self::STATEMENT_IBAN, 'R1'));
    }

    public function test_empty_and_malformed_input_is_refused(): void
    {
        $this->assertRefused('', 'expense_import_file_invalid');
        $this->assertRefused("  \n", 'expense_import_file_invalid');
        $this->assertRefused('<Document><unclosed>', 'expense_import_file_invalid');
        $this->assertRefused('not xml at all', 'expense_import_file_invalid');
    }

    public function test_other_formats_and_versions_are_refused(): void
    {
        $this->assertRefused($this->camtXml([$this->debit('A1')], namespace: 'urn:iso:std:iso:20022:tech:xsd:camt.052.001.02'), 'expense_import_unsupported_format');
        $this->assertRefused($this->camtXml([$this->debit('A1')], namespace: 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.08'), 'expense_import_unsupported_format');
        $this->assertRefused(str_replace('<Document', '<Other', str_replace('</Document>', '</Other>', $this->camtXml([$this->debit('A1')]))), 'expense_import_unsupported_format');
    }

    public function test_doctype_is_refused_and_entities_are_never_resolved(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'xxe');
        file_put_contents($secret, 'TOP-SECRET-CONTENT');
        $xxe = '<!DOCTYPE d [<!ENTITY xxe SYSTEM "file://'.$secret.'">]>';

        try {
            $this->assertRefused($this->camtXml([$this->debit('A1')], prolog: $xxe));

            try {
                $this->parser->parse(str_replace('Dodavatel A1', '&xxe;', $this->camtXml([$this->debit('A1')], prolog: $xxe)));
                $this->fail('Expected refusal.');
            } catch (ValidationException $e) {
                $this->assertStringNotContainsString('TOP-SECRET', json_encode($e->errors(), JSON_THROW_ON_ERROR));
            }
        } finally {
            unlink($secret);
        }
    }

    public function test_billion_laughs_is_refused_quickly(): void
    {
        $dtd = '<!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;"><!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;"><!ENTITY lol4 "&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;">]>';
        $xml = str_replace('M1', '&lol4;', $this->camtXml([$this->debit('A1')], prolog: $dtd));

        $started = microtime(true);
        $this->assertRefused($xml);

        $this->assertLessThan(2.0, microtime(true) - $started);
    }

    public function test_invalid_debits_refuse_the_whole_file(): void
    {
        $this->assertRefused($this->camtXml([$this->debit('A1'), $this->debit('', '5.00')]), 'expense_import_missing_reference');
        $this->assertRefused($this->camtXml([$this->debit('A1'), $this->debit(' ', '5.00')]), 'expense_import_missing_reference');
        $this->assertRefused($this->camtXml([$this->debit('A1'), $this->debit('A1', '5.00', '2026-10-02')]), 'expense_import_duplicate_reference');
        $this->assertRefused($this->camtXml([$this->debit('A1', '5.00', '2026-10-01', ['currency' => 'CZK'])]), 'expense_import_unsupported_currency');
        $this->assertRefused($this->camtXml([$this->debit('A1', '5.123')]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit('A1', '-5.00')]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit('A1', 'abc')]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit('A1', '5.00', '2026-13-40')]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit('A1', '5.00', '2026-02-30')]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit(str_repeat('x', 101))]), 'expense_import_file_invalid');
        $this->assertRefused($this->camtXml([$this->debit('A1', '5.00', '2026-10-01', ['direction' => 'XXXX'])]), 'expense_import_file_invalid');
    }

    public function test_credits_and_skipped_entries_need_no_reference_or_currency(): void
    {
        $statement = $this->parser->parse($this->camtXml([
            $this->credit('', '5.00', '2026-10-01', ['currency' => 'CZK', 'reference' => null]),
            $this->debit('', '5.00', '2026-10-01', ['reference' => null, 'status' => 'PDNG']),
            $this->debit('A1'),
        ]));

        $this->assertCount(1, $statement->debitRows);
    }

    public function test_the_iban_must_be_present_valid_and_the_same_in_every_statement(): void
    {
        $this->assertRefused($this->camtXml([$this->debit('A1')], iban: ''));
        $this->assertRefused($this->camtXml([$this->debit('A1')], iban: 'not-an-iban'));

        $xml = $this->camtXml([$this->debit('A1')]);
        $second = $this->camtStatement(self::OTHER_IBAN, $this->camtEntry($this->debit('B1')));
        $this->assertRefused(str_replace('</BkToCstmrStmt>', $second.'</BkToCstmrStmt>', $xml));

        $sameIban = $this->camtStatement(self::STATEMENT_IBAN, $this->camtEntry($this->debit('B1')));
        $statement = $this->parser->parse(str_replace('</BkToCstmrStmt>', $sameIban.'</BkToCstmrStmt>', $xml));
        $this->assertCount(2, $statement->debitRows);

        $withoutAccount = str_replace('<Acct><Id><IBAN>'.self::STATEMENT_IBAN.'</IBAN></Id><Ccy>EUR</Ccy></Acct>', '', $xml);
        $this->assertRefused($withoutAccount);
    }

    public function test_a_reference_repeated_across_statements_is_refused(): void
    {
        $xml = $this->camtXml([$this->debit('A1')]);
        $second = $this->camtStatement(self::STATEMENT_IBAN, $this->camtEntry($this->debit('A1')));

        $this->assertRefused(str_replace('</BkToCstmrStmt>', $second.'</BkToCstmrStmt>', $xml), 'expense_import_duplicate_reference');
    }

    public function test_row_count_above_the_cap_is_refused(): void
    {
        config(['expenses.statement.max_rows' => 3]);
        $entries = array_map(fn (int $i): array => $this->debit('R'.$i), range(1, 4));

        $this->assertCount(3, $this->parser->parse($this->camtXml(array_slice($entries, 0, 3)))->debitRows);

        try {
            $this->parser->parse($this->camtXml($entries));
            $this->fail('Expected refusal.');
        } catch (ValidationException $e) {
            $this->assertSame(__('app.expense_import_too_many_rows', ['max' => 3]), $e->validator->errors()->first('file'));
        }
    }

    public function test_a_statement_without_any_stmt_is_refused(): void
    {
        $this->assertRefused('<?xml version="1.0"?><Document xmlns="'.self::CAMT_NAMESPACE.'"><BkToCstmrStmt/></Document>');
    }

    /** @return array<string, mixed> */
    private function paying(string $reference, string $endToEnd = '/VS0000012345/SS/KS', string $amount = '25.00'): array
    {
        return $this->credit($reference, $amount, '2026-10-05', [
            'counterparty' => null,
            'payer' => 'Platca A',
            'payer_iban' => 'SK3333000000003333333333',
            'end_to_end' => $endToEnd,
        ]);
    }

    public function test_credit_is_parsed_with_variable_symbol_payer_and_period(): void
    {
        $statement = $this->parser->parse($this->camtXml(
            [$this->paying('C1')],
            period: ['2026-09-30T00:00:00.000+02:00', '2026-10-30T23:59:59.000+01:00'],
        ));

        $this->assertSame(1, $statement->skippedCreditRows);
        $this->assertCount(1, $statement->creditRows);
        $credit = $statement->creditRows[0];
        $this->assertSame('C1', $credit->reference);
        $this->assertSame('25.00', $credit->amount);
        $this->assertSame('2026-10-05', $credit->date);
        $this->assertSame('12345', $credit->variableSymbol);
        $this->assertSame('Platca A', $credit->counterparty);
        $this->assertSame('SK3333000000003333333333', $credit->counterpartyIban);
        $this->assertSame('Platba C1', $credit->note);
        $this->assertSame('2026-09-30', $statement->periodFrom);
        $this->assertSame('2026-10-30', $statement->periodTo);
    }

    public function test_credit_without_variable_symbol_has_none_and_period_is_optional(): void
    {
        $statement = $this->parser->parse($this->camtXml([
            $this->paying('C1', 'NOTPROVIDED'),
            $this->paying('C2', '/VS0000000000/SS'),
            $this->paying('C3', '/VS12345678901'),
        ]));

        $this->assertCount(3, $statement->creditRows);
        $this->assertNull($statement->creditRows[0]->variableSymbol);
        $this->assertNull($statement->creditRows[1]->variableSymbol);
        $this->assertNull($statement->creditRows[2]->variableSymbol);
        $this->assertNull($statement->periodFrom);
        $this->assertNull($statement->periodTo);
    }

    public function test_unreadable_credits_are_left_out_without_failing_the_file(): void
    {
        $statement = $this->parser->parse($this->camtXml([
            $this->debit('D1', '5.00'),
            $this->paying('C1'),
            $this->paying('C1', amount: '30.00'),
            $this->paying('C2', amount: '0.00'),
            $this->credit('C3', '10.00', '2026-10-05', ['currency' => 'CZK']),
            $this->credit('C4', '10.00', '2026-10-05', ['reference' => null]),
            $this->credit('C5', 'abc', '2026-10-05'),
            $this->paying('C6'),
        ]));

        $this->assertSame(7, $statement->skippedCreditRows);
        $this->assertCount(1, $statement->debitRows);
        $this->assertSame(['C1', 'C6'], array_map(static fn ($row): string => $row->reference, $statement->creditRows));
        $this->assertSame('25.00', $statement->creditRows[0]->amount);
    }
}
