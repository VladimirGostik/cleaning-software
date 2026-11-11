// Runtime enum value lists + label-key helpers.
// generated.d.ts ships only the TS union types (no runtime value) — these arrays mirror
// the PHP backed enums so pages can build select/radio options. Keep in sync manually:
// the TS union catches a wrong member here, but not a member missing from this list.

export const CLIENT_TYPES: readonly App.Enums.ClientTypeEnum[] = ['corporate', 'private'];

export const OBJECT_TYPES: readonly App.Enums.ObjectTypeEnum[] = [
    'office',
    'apartment',
    'house',
    'residential_building',
    'common_areas',
];

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

export const QUOTE_BILLING_PERIODS: readonly App.Enums.QuoteBillingPeriodEnum[] = [
    'one_off',
    'monthly',
    'quarterly',
    'yearly',
];

export function quoteBillingPeriodKey(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `quote_period_${period}`;
}

export function quoteBillingAdverbKey(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `quote_period_adverb_${period}`;
}

export function quoteBillingUnitKey(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `quote_period_unit_${period}`;
}

export function quoteBillingSuffixKey(period: App.Enums.QuoteBillingPeriodEnum): string {
    return `quote_period_suffix_${period}`;
}

export const RECURRING_STATUSES: readonly App.Enums.RecurringInvoiceStatusEnum[] = [
    'active',
    'paused',
    'completed',
    'cancelled',
];

export const RECURRING_BILLED_MONTHS: readonly App.Enums.RecurringBilledMonthEnum[] = ['none', 'current', 'previous'];

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

export function recurringBilledMonthKey(month: App.Enums.RecurringBilledMonthEnum): string {
    return `recurring_billed_month_${month}`;
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

export const CONTRACTABLE_TYPES: readonly App.Enums.ContractableTypeEnum[] = [
    'cleaning_object',
    'tenant_membership',
    'client',
];

export const CONTRACT_SOURCES: readonly App.Enums.ContractSourceEnum[] = ['generated', 'file'];

export const CONTRACT_FILE_CATEGORIES: readonly App.Enums.ContractCategoryEnum[] = [
    'service_agreement',
    'nda',
    'gdpr',
    'other',
];

export const CONTRACT_FILE_CONTRACTABLE_TYPES: readonly App.Enums.ContractableTypeEnum[] = [
    'cleaning_object',
    'client',
];

export function contractSourceKey(source: App.Enums.ContractSourceEnum): string {
    return `contract_source_${source}`;
}

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

export const EMPLOYEE_TYPES: readonly App.Enums.EmployeeTypeEnum[] = ['brigade', 'tpp', 'self_employed'];

export function employeeTypeKey(type: App.Enums.EmployeeTypeEnum): string {
    return `employee_type_${type}`;
}

const EMPLOYMENT_TYPE_BY_EMPLOYEE_TYPE: Record<App.Enums.EmployeeTypeEnum, App.Enums.EmploymentContractTypeEnum> = {
    brigade: 'dpp',
    tpp: 'tpp',
    self_employed: 'self_employed',
};

export function employmentTypeForEmployeeType(type: App.Enums.EmployeeTypeEnum): App.Enums.EmploymentContractTypeEnum {
    return EMPLOYMENT_TYPE_BY_EMPLOYEE_TYPE[type];
}

export function contractableTypeKey(type: App.Enums.ContractableTypeEnum): string {
    return `contractable_type_${type}`;
}

// Mirrors ContractCategoryEnum::allowedContractableTypes() (BE).
export const CONTRACT_CATEGORY_CONTRACTABLE_TYPES: Record<
    App.Enums.ContractCategoryEnum,
    readonly App.Enums.ContractableTypeEnum[]
> = {
    service_agreement: ['cleaning_object', 'client'],
    employment: ['tenant_membership'],
    nda: CONTRACTABLE_TYPES,
    gdpr: CONTRACTABLE_TYPES,
    other: CONTRACTABLE_TYPES,
};

// Mirrors DocumentTypeEnum::templatable() (BE).
export const TEMPLATABLE_DOCUMENT_TYPES: readonly App.Enums.DocumentTypeEnum[] = [
    'service_agreement',
    'employment',
    'nda',
    'gdpr',
    'other',
    'quote',
];

// Mirrors DocumentTypeEnum::employeeFiles() (BE).
export const EMPLOYEE_FILE_TYPES: readonly App.Enums.DocumentTypeEnum[] = [
    'employee_id_card',
    'employee_passport',
    'employee_residence_permit',
    'employee_work_permit',
    'employee_medical',
    'employee_training',
    'employee_other',
];

export const DOCUMENT_TOKEN_GROUPS: readonly App.Enums.DocumentTokenGroupEnum[] = [
    'tenant',
    'contract',
    'client',
    'object',
    'employee',
    'employment',
    'customer',
    'quote',
    'conditions',
    'blocks',
];

export const DOCUMENT_TEMPLATE_SCOPES: readonly App.Enums.DocumentTemplateScopeEnum[] = ['global', 'override', 'firm'];

export const DOCUMENT_TEMPLATE_STATES: readonly App.Enums.DocumentTemplateStateEnum[] = [
    'base',
    'customized',
    'base_changed',
];

export function documentTypeKey(type: App.Enums.DocumentTypeEnum): string {
    return `document_type_${type}`;
}

export function documentTokenGroupKey(group: App.Enums.DocumentTokenGroupEnum): string {
    return `document_token_group_${group}`;
}

export function documentTemplateScopeKey(scope: App.Enums.DocumentTemplateScopeEnum): string {
    return `document_template_scope_${scope}`;
}

export function documentTemplateStateKey(state: App.Enums.DocumentTemplateStateEnum): string {
    return `document_template_state_${state}`;
}

export const JOB_TYPES: readonly App.Enums.JobTypeEnum[] = ['regular', 'one_off', 'special'];

export const JOB_STATUSES: readonly App.Enums.JobStatusEnum[] = [
    'unassigned',
    'planned',
    'in_progress',
    'completed',
    'unapproved',
    'cancelled',
];

export const TASK_FREQUENCIES: readonly App.Enums.TaskFrequencyEnum[] = [
    'one_time',
    'weekly_1x',
    'weekly_2x',
    'weekly_3x',
    'biweekly',
    'monthly',
    'bimonthly',
    'seasonal',
];

export function jobTypeKey(type: App.Enums.JobTypeEnum): string {
    return `job_type_${type}`;
}

export function jobStatusKey(status: App.Enums.JobStatusEnum): string {
    return `job_status_${status}`;
}

export function taskFrequencyKey(frequency: App.Enums.TaskFrequencyEnum): string {
    return `task_frequency_${frequency}`;
}

// Mirrors PermissionEnum::label() (BE) — flat "view employees" -> "permission_view_employees".
export function permissionLabelKey(name: App.Enums.PermissionEnum): string {
    return `permission_${name.replace(/\s+/g, '_')}`;
}

// Mirrors BE NotificationTypeEnum::key() — 'invoice.overdue' -> 'notification_type_invoice_overdue'.
export function notificationTypeKey(type: App.Enums.NotificationTypeEnum): string {
    return `notification_type_${type.replace('.', '_')}`;
}

// 'invoice.overdue' -> 'notification_settings_desc_invoice_overdue'
export function notificationPreferenceDescKey(type: App.Enums.NotificationTypeEnum): string {
    return `notification_settings_desc_${type.replace('.', '_')}`;
}

// BE plan lang keys 'dashboard_alert_<type>' (shared app.json); no runtime value list needed —
// alert order is driven by BE `alert_counts`, not by this file.
export function dashboardAlertTypeKey(type: App.Enums.DashboardAlertTypeEnum): string {
    return `dashboard_alert_${type}`;
}

export const TASK_STATUSES: readonly App.Enums.TaskStatusEnum[] = [
    'open',
    'in_progress',
    'waiting',
    'nice_to_have',
    'done',
];

export const TASK_ACTIVE_STATUSES: readonly App.Enums.TaskStatusEnum[] = [
    'open',
    'in_progress',
    'waiting',
    'nice_to_have',
];

export const TASK_PRIORITIES: readonly App.Enums.TaskPriorityEnum[] = ['low', 'normal', 'high'];

export function taskStatusKey(status: App.Enums.TaskStatusEnum): string {
    return `task_status_${status}`;
}

export function taskRunStatusKey(status: App.Enums.TaskRunStatusEnum): string {
    return `task_run_status_${status}`;
}

export function taskPriorityKey(priority: App.Enums.TaskPriorityEnum): string {
    return `task_priority_${priority}`;
}

export const EXPENSE_CATEGORIES: readonly App.Enums.ExpenseCategoryEnum[] = [
    'fuel',
    'materials',
    'cleaning_supplies',
    'equipment',
    'services',
    'rent',
    'utilities',
    'wages',
    'payroll_levies',
    'insurance',
    'fees',
    'bank_charges',
    'other',
];

export const EXPENSE_STATUSES: readonly App.Enums.ExpenseStatusEnum[] = ['draft', 'confirmed'];

export const EXPENSE_SOURCES: readonly App.Enums.ExpenseSourceEnum[] = ['manual', 'receipt', 'bank_statement', 'mcp'];

export function expenseCategoryKey(category: App.Enums.ExpenseCategoryEnum): string {
    return `expense_category_${category}`;
}

export function expenseStatusKey(status: App.Enums.ExpenseStatusEnum): string {
    return `expense_status_${status}`;
}

export function expenseSourceKey(source: App.Enums.ExpenseSourceEnum): string {
    return `expense_source_${source}`;
}

export function taskReminderSlotKey(slot: App.Enums.TaskReminderSlotEnum): string {
    return `task_reminder_slot_${slot}`;
}
