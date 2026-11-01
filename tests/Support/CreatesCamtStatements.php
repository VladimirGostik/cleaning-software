<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

trait CreatesCamtStatements
{
    protected const string STATEMENT_IBAN = 'SK1111000000001111111111';

    protected const string OTHER_IBAN = 'SK2222000000002222222222';

    protected const string CAMT_NAMESPACE = 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function debit(string $reference, string $amount = '10.00', string $date = '2026-10-01', array $overrides = []): array
    {
        return [
            'direction' => 'DBIT',
            'status' => 'BOOK',
            'reversal' => 'false',
            'reference' => $reference,
            'amount' => $amount,
            'currency' => 'EUR',
            'date' => $date,
            'counterparty' => 'Dodavatel '.$reference,
            'note' => 'Platba '.$reference,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function credit(string $reference, string $amount = '10.00', string $date = '2026-10-01', array $overrides = []): array
    {
        return $this->debit($reference, $amount, $date, ['direction' => 'CRDT', ...$overrides]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    protected function camtXml(array $entries, string $iban = self::STATEMENT_IBAN, string $namespace = self::CAMT_NAMESPACE, string $prolog = ''): string
    {
        $body = '';

        foreach ($entries as $entry) {
            $body .= $this->camtEntry($entry);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'.$prolog
            .'<Document xmlns="'.$namespace.'"><BkToCstmrStmt><GrpHdr><MsgId>M1</MsgId></GrpHdr>'
            .$this->camtStatement($iban, $body)
            .'</BkToCstmrStmt></Document>';
    }

    protected function camtStatement(string $iban, string $entriesXml): string
    {
        return '<Stmt><Id>S1</Id><Acct><Id><IBAN>'.$iban.'</IBAN></Id><Ccy>EUR</Ccy></Acct>'.$entriesXml.'</Stmt>';
    }

    /** @param  array<string, mixed>  $entry */
    protected function camtEntry(array $entry): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $reference = array_key_exists('reference', $entry) && $entry['reference'] !== null ? '<NtryRef>'.$e($entry['reference']).'</NtryRef>' : '';
        $booking = array_key_exists('datetime', $entry)
            ? '<BookgDt><DtTm>'.$e($entry['datetime']).'</DtTm></BookgDt>'
            : '<BookgDt><Dt>'.$e($entry['date']).'</Dt></BookgDt>';
        $counterparty = $entry['counterparty'] === null ? '' : '<RltdPties><Cdtr><Nm>'.$e($entry['counterparty']).'</Nm></Cdtr></RltdPties>';
        $note = $entry['note'] === null ? '' : '<RmtInf><Ustrd>'.$e($entry['note']).'</Ustrd></RmtInf>';
        $details = '<TxDtls><Refs><EndToEndId>NOTPROVIDED</EndToEndId></Refs>'.$counterparty.$note.'</TxDtls>';

        if (($entry['tx_details'] ?? 1) === 2) {
            $details .= $details;
        }

        return '<Ntry>'.$reference
            .'<Amt Ccy="'.$e($entry['currency']).'">'.$e($entry['amount']).'</Amt>'
            .'<CdtDbtInd>'.$e($entry['direction']).'</CdtDbtInd>'
            .'<RvslInd>'.$e($entry['reversal']).'</RvslInd>'
            .'<Sts>'.$e($entry['status']).'</Sts>'
            .$booking
            .'<NtryDtls>'.$details.'</NtryDtls></Ntry>';
    }

    protected function camtFile(string $xml, string $name = 'vypis.xml'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $xml);
    }
}
