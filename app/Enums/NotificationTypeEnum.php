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
        return __('app.notification_type.' . $this->value);
    }

    public function defaultMailEnabled(): bool
    {
        return match ($this) {
            self::InvoiceOverdue => true,
            default => false,
        };
    }

    public function userConfigurable(): bool
    {
        return match ($this) {
            self::InvitationCreated, self::InvoiceIssued => false,
            default => true,
        };
    }

    /**
     * External, mail-only types never create an in-app (database) notification row.
     */
    public function deliversInApp(): bool
    {
        return match ($this) {
            self::InvitationCreated, self::InvoiceIssued => false,
            default => true,
        };
    }

    /**
     * Type filter options for the notification centre (only in-app types).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function inAppOptions(): array
    {
        return array_values(array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            array_filter(self::cases(), fn (self $case) => $case->deliversInApp()),
        ));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
