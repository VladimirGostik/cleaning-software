<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceSettingsData;
use App\Data\Tenants\TenantSupplierProfileData;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final readonly class InvoiceSettingsService
{
    public function __construct(
        private DatabaseManager $db,
        private TemporaryUploadService $uploads,
    ) {}

    public function update(Tenant $tenant, InvoiceSettingsData $data, ?User $actor, string $sessionId): void
    {
        $this->db->transaction(function () use ($tenant, $data, $actor, $sessionId): void {
            $attributes = [
                'name' => $data->name,
                'ico' => $data->ico,
                'dic' => $data->dic,
                'vat_number' => $data->vat_number,
                'is_vat_payer' => $data->is_vat_payer,
                'address_line' => $data->address_line,
                'city' => $data->city,
                'postal_code' => $data->postal_code,
                'country' => $data->country,
                'contact_email' => $data->contact_email,
                'contact_phone' => $data->contact_phone,
                'invoice_number_format' => $data->invoice_number_format,
                'iban' => $data->iban,
                'swift_bic' => $data->swift_bic,
                'registration_info' => $data->registration_info,
                'representative_name' => $this->blankToNull($data->representative_name),
                'peppol_participant_id' => $this->blankToNull($data->peppol_participant_id),
                'signing_place' => $this->blankToNull($data->signing_place),
            ];

            // Only update vat_rate when explicitly provided — column is NOT NULL with DB default 23
            if ($data->vat_rate !== null) {
                $attributes['vat_rate'] = $data->vat_rate;
            }

            $tenant->update($attributes);

            if ($data->signature_uuid !== null) {
                $media = $this->uploads->moveToModel($tenant, 'signature', $data->signature_uuid, $actor, $sessionId);
                $tenant->update(['signature_media_id' => $media->id]);
            } elseif ($data->remove_signature) {
                $tenant->update(['signature_media_id' => null]);
            }

            if ($data->logo_uuid !== null) {
                $this->uploads->moveToModel($tenant, 'logo', $data->logo_uuid, $actor, $sessionId);
            } elseif ($data->remove_logo) {
                $tenant->clearMediaCollection('logo');
            }

            $interfaceFields = [
                'recurring_default_state' => $data->recurring_default_state,
                'default_constant_symbol' => $data->default_constant_symbol,
                'default_payment_type' => $data->default_payment_type,
                'default_currency' => $data->default_currency,
                'default_rounding_mode' => $data->default_rounding_mode,
                'default_header_text' => $data->default_header_text,
                'default_footer_text' => $data->default_footer_text,
                'default_quote_included_text' => $this->blankToNull($data->default_quote_included_text),
                'invoice_mail_subject' => $this->blankToNull($data->invoice_mail_subject),
                'invoice_mail_body' => $this->blankToNull($data->invoice_mail_body),
            ];

            if ($tenant->interface !== null) {
                $tenant->interface->update($interfaceFields);
            } else {
                TenantInterface::create(array_merge(['tenant_id' => $tenant->id], $interfaceFields));
            }
        });
    }

    public function fillBlanks(Tenant $target, TenantSupplierProfileData $profile, ?Tenant $source): void
    {
        $this->db->transaction(function () use ($target, $profile, $source): void {
            $values = [
                'address_line' => $profile->address_line,
                'city' => $profile->city,
                'postal_code' => $profile->postal_code,
                'dic' => $profile->dic,
                'iban' => $profile->iban,
            ];

            if ($source !== null) {
                $values += [
                    'contact_email' => $source->contact_email,
                    'contact_phone' => $source->contact_phone,
                    'representative_name' => $source->representative_name,
                    'signing_place' => $source->signing_place,
                ];
            }

            $target->update($this->onlyBlankTargets($target, $values));

            if ($source?->interface === null) {
                return;
            }

            $interface = $target->interface ?? TenantInterface::create(['tenant_id' => $target->id]);

            $interface->update($this->onlyBlankTargets($interface, [
                'default_header_text' => $source->interface->default_header_text,
                'default_footer_text' => $source->interface->default_footer_text,
                'default_quote_included_text' => $source->interface->default_quote_included_text,
                'invoice_mail_subject' => $source->interface->invoice_mail_subject,
                'invoice_mail_body' => $source->interface->invoice_mail_body,
            ]));
        });
    }

    public function copyMissingMedia(Tenant $source, Tenant $target): void
    {
        $logo = $source->logoMedia();

        if ($logo !== null && $target->logoMedia() === null) {
            $logo->copy($target, 'logo', $this->diskFor('invoicing.logo.disk'));
        }

        $signature = $source->signatureMedia;

        if ($signature !== null && $target->signature_media_id === null) {
            $copy = $signature->copy($target, 'signature', $this->diskFor('invoicing.signature.disk'));

            try {
                $target->update(['signature_media_id' => $copy->id]);
            } catch (Throwable $e) {
                $copy->delete();

                throw $e;
            }
        }
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private function onlyBlankTargets(Model $target, array $values): array
    {
        $fill = [];

        foreach ($values as $column => $value) {
            $current = $target->getAttribute($column);

            if ($value !== null && $value !== '' && ($current === null || (is_string($current) && trim($current) === ''))) {
                $fill[$column] = $value;
            }
        }

        return $fill;
    }

    private function diskFor(string $key): string
    {
        $disk = config($key, 'local');

        return is_string($disk) ? $disk : 'local';
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
