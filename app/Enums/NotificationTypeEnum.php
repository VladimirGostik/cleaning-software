<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum NotificationTypeEnum: string
{
    case InvitationCreated = 'invitation.created';
    case InvoiceIssued = 'invoice.issued';
    case InvoiceOverdue = 'invoice.overdue';
    case ContractExpiring = 'contract.expiring';
    case ContractExpired = 'contract.expired';
    case QuoteSent = 'quote.sent';
    case QuoteExpiring = 'quote.expiring';
    case QuoteExpired = 'quote.expired';

    public function label(): string
    {
        return __('app.notification_type_'.$this->key());
    }

    /** Whether mail delivery is on by default when the user has no stored preference. */
    public function defaultMailEnabled(): bool
    {
        return $this === self::InvoiceOverdue;
    }

    /** Whether the user may toggle mail delivery for this type in Settings. */
    public function userConfigurable(): bool
    {
        return ! in_array($this, [self::InvitationCreated, self::InvoiceIssued], true);
    }

    /** Whether this type ever writes an in-app (database channel) row. */
    public function deliversInApp(): bool
    {
        return ! in_array($this, [self::InvitationCreated, self::InvoiceIssued], true);
    }

    /**
     * Whether mail is unconditionally sent for this type, bypassing user preferences
     * entirely (invitations, invoice-sent receipts — mail-only, non-configurable).
     */
    public function alwaysMail(): bool
    {
        return in_array($this, [self::InvitationCreated, self::InvoiceIssued], true);
    }

    /** @return list<self> */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => $case->userConfigurable()));
    }

    /** @return list<string> */
    public static function configurableValues(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::configurable());
    }

    /** @return list<array{value: string, label: string}> */
    public static function inAppOptions(): array
    {
        $cases = array_values(array_filter(self::cases(), static fn (self $case): bool => $case->deliversInApp()));

        return array_map(static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }

    /** Flat lang-key fragment — `invoice.overdue` becomes `invoice_overdue`. */
    private function key(): string
    {
        return str_replace('.', '_', $this->value);
    }
}
