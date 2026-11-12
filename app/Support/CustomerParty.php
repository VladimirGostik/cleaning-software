<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Quotes\QuoteUpsertData;
use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\CustomerPartySourceEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;

final readonly class CustomerParty
{
    public function __construct(
        public ?string $name = null,
        public ?string $representative = null,
        public ?string $representedBy = null,
        public ?string $ico = null,
        public ?string $peppolParticipantId = null,
        public ?string $dic = null,
        public ?string $vatNumber = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public ?string $email = null,
        public CustomerPartySourceEnum $source = CustomerPartySourceEnum::Entered,
    ) {}

    public static function fromEntered(InvoiceUpsertData|RecurringInvoiceUpsertData|Invoice|RecurringInvoice $source): self
    {
        return new self(
            name: $source->customer_name,
            representative: $source->customer_representative,
            ico: $source->customer_ico,
            dic: $source->customer_dic,
            vatNumber: $source->customer_vat_number,
            street: $source->customer_street,
            city: $source->customer_city,
            postalCode: $source->customer_postal_code,
            country: $source->customer_country,
            email: $source->customer_email,
        );
    }

    public static function fromQuote(Quote|QuoteUpsertData $source): self
    {
        return new self(
            name: $source->customer_name,
            representedBy: $source->customer_represented_by,
            ico: $source->customer_ico,
            dic: $source->customer_dic,
            street: $source->customer_street,
            city: $source->customer_city,
            postalCode: $source->customer_postal_code,
            email: $source->customer_email,
        );
    }

    /** Whole buyer from the object billing data when it has a billing name, else whole buyer from the client. */
    public static function forSubject(?CleaningObject $object, ?Client $client): ?self
    {
        if ($object?->hasBillingIdentity()) {
            return new self(
                name: $object->billing_name,
                ico: $object->billing_ico,
                peppolParticipantId: $object->billing_peppol_participant_id,
                dic: $object->billing_dic,
                vatNumber: $object->billing_vat_number,
                street: $object->billing_street,
                city: $object->billing_city,
                postalCode: $object->billing_postal_code,
                country: $object->country,
                email: $object->billing_email,
                source: CustomerPartySourceEnum::ObjectBilling,
            );
        }

        if ($client !== null) {
            return new self(
                name: $client->name,
                ico: $client->ico,
                peppolParticipantId: $client->peppol_participant_id,
                dic: $client->dic,
                vatNumber: $client->vat_number,
                street: $client->street,
                city: $client->city,
                postalCode: $client->postal_code,
                country: $client->country,
                source: CustomerPartySourceEnum::Client,
            );
        }

        return null;
    }

    private function with(?string $representative = null, ?string $email = null): self
    {
        return new self(
            name: $this->name,
            representative: $representative,
            representedBy: $this->representedBy,
            ico: $this->ico,
            peppolParticipantId: $this->peppolParticipantId,
            dic: $this->dic,
            vatNumber: $this->vatNumber,
            street: $this->street,
            city: $this->city,
            postalCode: $this->postalCode,
            country: $this->country,
            email: $email,
            source: $this->source,
        );
    }

    public static function forQuote(Quote $quote): self
    {
        $party = self::forSubject($quote->cleaningObject, $quote->client);

        if ($party === null) {
            return self::fromQuote($quote);
        }

        return $party->with(email: $quote->customer_email);
    }

    public static function forInvoice(?CleaningObject $object, ?Client $client, self $entered): self
    {
        $party = self::forSubject($object, $client);

        if ($party !== null) {
            return $party->with(representative: $entered->representative, email: $entered->email);
        }

        return new self(
            name: $entered->name ?? '',
            representative: $entered->representative,
            ico: $entered->ico,
            dic: $entered->dic,
            vatNumber: $entered->vatNumber,
            street: $entered->street,
            city: $entered->city,
            postalCode: $entered->postalCode,
            country: $entered->country ?? 'SK',
            email: $entered->email,
        );
    }

    /** @return array<string, ?string> */
    public function toInvoiceAttributes(): array
    {
        return [
            ...$this->toEnteredAttributes(),
            'customer_represented_by' => $this->representedBy,
            'customer_peppol_participant_id' => $this->peppolParticipantId,
        ];
    }

    /** @return array<string, ?string> */
    public function toEnteredAttributes(): array
    {
        return [
            'customer_name' => $this->name,
            'customer_representative' => $this->representative,
            'customer_ico' => $this->ico,
            'customer_dic' => $this->dic,
            'customer_vat_number' => $this->vatNumber,
            'customer_street' => $this->street,
            'customer_city' => $this->city,
            'customer_postal_code' => $this->postalCode,
            'customer_country' => $this->country,
            'customer_email' => $this->email,
        ];
    }

    /** @return array<string, ?string> */
    public function toQuoteAttributes(): array
    {
        return [
            'customer_name' => $this->name,
            'customer_email' => $this->email,
            'customer_street' => $this->street,
            'customer_city' => $this->city,
            'customer_postal_code' => $this->postalCode,
            'customer_represented_by' => $this->representedBy,
            'customer_ico' => $this->ico,
            'customer_dic' => $this->dic,
        ];
    }
}
