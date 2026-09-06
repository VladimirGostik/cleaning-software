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

export const QUOTE_STATUSES: readonly App.Enums.QuoteStatusEnum[] = [
    'draft',
    'sent',
    'accepted',
    'rejected',
    'expired',
];

export const QUOTE_KINDS: readonly App.Enums.QuoteKindEnum[] = ['itemized', 'document'];

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

export function quoteStatusKey(status: App.Enums.QuoteStatusEnum): string {
    return `quote_status_${status}`;
}

export function quoteKindKey(kind: App.Enums.QuoteKindEnum): string {
    return `quote_kind_${kind}`;
}

export function enumOptions<T extends string>(
    values: readonly T[],
    keyFn: (value: T) => string,
    t: (key: string) => string,
): { value: T; label: string }[] {
    return values.map((value) => ({ value, label: t(keyFn(value)) }));
}

export const CONTRACT_STATUSES: readonly App.Enums.ContractStatusEnum[] = ['draft', 'active', 'expired', 'terminated'];

export const CONTRACT_CATEGORIES: readonly App.Enums.ContractCategoryEnum[] = [
    'service_agreement',
    'employment',
    'nda',
    'gdpr',
    'other',
];

export const CONTRACT_TERM_TYPES: readonly App.Enums.ContractTermTypeEnum[] = ['fixed', 'indefinite'];

export const EMPLOYMENT_CONTRACT_TYPES: readonly App.Enums.EmploymentContractTypeEnum[] = [
    'dpp',
    'dpc',
    'tpp',
    'self_employed',
];

export const CONTRACTABLE_TYPES: readonly App.Enums.ContractableTypeEnum[] = ['cleaning_object', 'tenant_membership'];

export function contractStatusKey(status: App.Enums.ContractStatusEnum): string {
    return `contract_status_${status}`;
}

export function contractCategoryKey(category: App.Enums.ContractCategoryEnum): string {
    return `contract_category_${category}`;
}

export function contractTermTypeKey(termType: App.Enums.ContractTermTypeEnum): string {
    return `contract_term_type_${termType}`;
}

export function employmentTypeKey(type: App.Enums.EmploymentContractTypeEnum): string {
    return `employment_type_${type}`;
}

export function contractableTypeKey(type: App.Enums.ContractableTypeEnum): string {
    return `contractable_type_${type}`;
}

// Mirrors ContractCategoryEnum::expectedContractableType() (BE) — null means either party type is allowed.
export const CONTRACT_CATEGORY_CONTRACTABLE: Record<
    App.Enums.ContractCategoryEnum,
    App.Enums.ContractableTypeEnum | null
> = {
    service_agreement: 'cleaning_object',
    employment: 'tenant_membership',
    nda: null,
    gdpr: null,
    other: null,
};
