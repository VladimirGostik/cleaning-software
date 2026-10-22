<?php

declare(strict_types=1);

namespace App\Services\Efa;

use App\Contracts\SubmitsEInvoices;
use App\Data\Invoices\EInvoiceStatusData;
use App\Enums\EfaStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class EInvoiceService
{
    public function __construct(
        private UblInvoiceXmlBuilder $builder,
        private SubmitsEInvoices $accessPoint,
        private DatabaseManager $db,
    ) {}

    public function status(Invoice $invoice): EInvoiceStatusData
    {
        $missing = $invoice->status === InvoiceStatusEnum::Draft ? [] : $this->builder->readiness($invoice);

        return EInvoiceStatusData::fromInvoice($invoice, $missing, ! $this->accessPoint instanceof NullEInvoiceAccessPoint);
    }

    public function xml(Invoice $invoice): string
    {
        $xml = $this->render($invoice);

        if ($invoice->efa_status === EfaStatusEnum::None) {
            $invoice->update(['efa_status' => EfaStatusEnum::Ready]);
        }

        return $xml;
    }

    private function render(Invoice $invoice): string
    {
        if ($invoice->status === InvoiceStatusEnum::Draft) {
            throw ValidationException::withMessages(['status' => [__('app.efa_invoice_is_draft')]]);
        }

        $missing = $this->builder->readiness($invoice);

        if ($missing !== []) {
            $labels = array_map(fn (string $field): string => __('app.efa_missing_'.$field), $missing);

            throw ValidationException::withMessages(['efa' => [__('app.efa_not_ready', ['fields' => implode(', ', $labels)])]]);
        }

        return $this->builder->build($invoice);
    }

    /**
     * Runs in HTTP context: the Media tenant_id hook needs a bound tenant. The row is locked and flipped to
     * Submitted before the provider call so concurrent submits cannot both pass; media is written only after
     * the provider answered.
     */
    public function submit(Invoice $invoice): Invoice
    {
        $xml = $this->render($invoice);

        [$locked, $previous] = $this->db->transaction(function () use ($invoice): array {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->efa_status->canSubmit()) {
                throw ValidationException::withMessages(['efa' => [__('app.efa_already_submitted')]]);
            }

            $previous = $locked->efa_status;
            $locked->update(['efa_status' => EfaStatusEnum::Submitted]);

            return [$locked, $previous];
        });

        try {
            $result = $this->accessPoint->submit($locked, $xml);
        } catch (ValidationException $e) {
            $locked->update(['efa_status' => $previous]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('E-invoice submission failed', ['invoice_id' => $locked->id, 'exception' => $e]);
            $locked->update(['efa_status' => EfaStatusEnum::Failed, 'efa_last_error' => $this->safeError($e)]);

            return $locked;
        }

        return $this->db->transaction(function () use ($locked, $xml, $result): Invoice {
            $fileName = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $locked->number).'.xml';

            $locked->addMediaFromString($xml)
                ->usingFileName($fileName)
                ->toMediaCollection('efa_xml');

            $locked->update([
                'efa_status' => $result->status,
                'efa_id' => $result->providerId,
                'efa_submitted_at' => now(),
                'efa_last_error' => $result->accepted ? null : Str::limit((string) $result->message, 255, ''),
            ]);

            return $locked;
        });
    }

    private function safeError(Throwable $e): string
    {
        $code = $e->getCode();
        $suffix = is_int($code) && $code !== 0 ? ' ('.$code.')' : '';

        return Str::limit(__('app.efa_submit_failed').$suffix, 255, '');
    }
}
