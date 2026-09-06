// Runtime enum value lists + label-key helpers.
// generated.d.ts ships only the TS union types (no runtime value) — these arrays mirror
// the PHP backed enums so pages can build select/radio options. Keep in sync manually:
// the TS union catches a wrong member here, but not a member missing from this list.

export const CLIENT_TYPES: readonly App.Enums.ClientTypeEnum[] = ['corporate', 'private'];

export const OBJECT_TYPES: readonly App.Enums.ObjectTypeEnum[] = ['office', 'apartment', 'house', 'common_areas'];

export function clientTypeKey(type: App.Enums.ClientTypeEnum): string {
    return `client_type_${type}`;
}

export function objectTypeKey(type: App.Enums.ObjectTypeEnum): string {
    return `object_type_${type}`;
}

export const INVOICE_STATUSES: readonly App.Enums.InvoiceStatusEnum[] = [
    'draft',
    'issued',
    'paid',
    'overdue',
    'cancelled',
];

export const INVOICE_TYPES: readonly App.Enums.InvoiceTypeEnum[] = ['monthly', 'one_off', 'special'];

export const INVOICE_TEMPLATES: readonly App.Enums.InvoiceTemplateEnum[] = ['classic', 'modern', 'minimal'];

export const PAYMENT_TYPES: readonly App.Enums.PaymentTypeEnum[] = ['transfer', 'cash', 'card', 'cod', 'other'];

export const CURRENCIES: readonly App.Enums.CurrencyEnum[] = ['EUR', 'CZK', 'USD'];

export const ROUNDING_MODES: readonly App.Enums.RoundingModeEnum[] = ['none', 'document', 'cash_005'];

export const RECURRING_FREQUENCIES: readonly App.Enums.RecurringFrequencyEnum[] = [
    'monthly',
    'every_2_months',
    'quarterly',
    'semi_annually',
    'annually',
];

export const RECURRING_STATUSES: readonly App.Enums.RecurringInvoiceStatusEnum[] = [
    'active',
    'paused',
    'completed',
    'cancelled',
];

export const RECURRING_DEFAULT_STATES: readonly App.Enums.RecurringDefaultStateEnum[] = ['draft', 'issued'];

export function invoiceStatusKey(status: App.Enums.InvoiceStatusEnum): string {
    return `invoice_status_${status}`;
}

export function invoiceTypeKey(type: App.Enums.InvoiceTypeEnum): string {
    return `invoice_type_${type}`;
}

export function invoiceTemplateKey(template: App.Enums.InvoiceTemplateEnum): string {
    return `invoice_template_${template}`;
}

export function paymentTypeKey(type: App.Enums.PaymentTypeEnum): string {
    return `payment_type_${type}`;
}

export function currencyKey(currency: App.Enums.CurrencyEnum): string {
    return `currency_${currency.toLowerCase()}`;
}

export function roundingModeKey(mode: App.Enums.RoundingModeEnum): string {
    return `rounding_mode_${mode}`;
}

export function recurringFrequencyKey(frequency: App.Enums.RecurringFrequencyEnum): string {
    return `recurring_frequency_${frequency}`;
}

export function recurringStatusKey(status: App.Enums.RecurringInvoiceStatusEnum): string {
    return `recurring_status_${status}`;
}

export function recurringDefaultStateKey(state: App.Enums.RecurringDefaultStateEnum): string {
    return `recurring_default_state_${state}`;
}

export function enumOptions<T extends string>(
    values: readonly T[],
    keyFn: (value: T) => string,
    t: (key: string) => string,
): { value: T; label: string }[] {
    return values.map((value) => ({ value, label: t(keyFn(value)) }));
}
