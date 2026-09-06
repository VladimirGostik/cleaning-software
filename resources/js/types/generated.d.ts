declare namespace App {
namespace Data {
export type ActivityLogDetailData = {
readonly id: number,
readonly log_name: string | null,
readonly description: string,
readonly subject_type: string | null,
readonly subject_id: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly properties: Record<string, any> | null,
readonly attribute_changes: Record<string, any> | null,
readonly created_at: string,
};
export type ActivityLogIndexFilterData = {
readonly search: string | null,
readonly subject_type: string | null,
readonly user_filter: string | null,
readonly date_from: string | null,
readonly date_to: string | null,
readonly sort: string | null,
readonly perPage: number | null,
};
export type ActivityLogListItemData = {
readonly id: number,
readonly log_name: string | null,
readonly description: string,
readonly subject_type: string | null,
readonly subject_id: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly created_at: string,
};
export type AuthTokenData = {
readonly token: string,
readonly user: App.Data.UserListItemData,
};
export type ChangePasswordData = {
readonly current_password: string,
readonly password: string,
readonly password_confirmation: string,
};
export type CreateRoleData = {
readonly name: string,
readonly permissions: string[],
};
export type CreateUserData = {
readonly name: string,
readonly email: string,
readonly password: string | null,
readonly password_confirmation: string | null,
readonly is_active: boolean,
readonly roles: string[],
};
export type LanguageSwitchData = {
readonly locale: string,
};
export type LoginData = {
readonly email: string,
readonly password: string,
readonly remember: boolean,
};
export type MediaDetailData = {
readonly id: number,
readonly uuid: string | null,
readonly file_name: string,
readonly name: string,
readonly size: number,
readonly mime_type: string | null,
readonly collection_name: string,
readonly disk: string,
readonly custom_properties: Record<string, any>,
readonly model_type: string,
readonly model_type_label: string,
readonly model_id: string | null,
readonly model_url: string | null,
readonly url: string,
readonly created_at: string,
};
export type MediaFileData = {
readonly uuid: string,
readonly file_name: string,
readonly mime_type: string | null,
readonly size: number,
readonly download_url: string,
};
export type MediaIndexFilterData = {
readonly search: string | null,
readonly model_type: string | null,
readonly collection_name: string | null,
readonly mime_type: string | null,
readonly sort: string | null,
readonly per_page: number | null,
};
export type MediaListItemData = {
readonly id: number,
readonly uuid: string | null,
readonly file_name: string,
readonly name: string,
readonly mime_type: string | null,
readonly size: number,
readonly collection_name: string,
readonly disk: string,
readonly model_type_label: string,
readonly model_id: string | null,
readonly model_url: string | null,
readonly url: string,
readonly created_at: string,
};
export type NavigationItemData = {
readonly key: string,
readonly label: string,
readonly href: string,
readonly icon: string,
readonly order: number,
readonly children: App.Data.NavigationItemData[],
};
export type NewPasswordData = {
readonly token: string,
readonly email: string,
readonly password: string,
readonly password_confirmation: string,
};
export type PasswordResetLinkData = {
readonly email: string,
};
export type PermissionGroupData = {
readonly group: string,
readonly group_label: string,
readonly permissions: App.Data.PermissionItemData[],
};
export type PermissionItemData = {
readonly id: string,
readonly name: App.Enums.PermissionEnum,
readonly label: string,
};
export type RoleDetailData = {
readonly id: string,
readonly name: string,
readonly permissions: string[],
readonly users_count: number,
readonly is_system: boolean,
};
export type RoleIndexFilterData = {
readonly search: string | null,
readonly per_page: number | null,
};
export type RoleListItemData = {
readonly id: string,
readonly name: string,
readonly permissions_count: number,
readonly users_count: number,
readonly is_system: boolean,
};
export type StoreTemporaryUploadData = {
readonly file: File,
};
export type UpdateProfileData = {
readonly name: string,
readonly email: string,
readonly locale: string,
};
export type UpdateRoleData = {
readonly name: string,
readonly permissions: string[],
};
export type UpdateUserData = {
readonly name: string,
readonly email: string,
readonly is_active: boolean,
readonly roles: string[],
};
export type UserAutocompleteItemData = {
readonly id: string,
readonly name: string,
readonly email: string,
};
export type UserIndexFilterData = {
readonly search: string | null,
readonly per_page: number | null,
};
export type UserListItemData = {
readonly id: string,
readonly name: string,
readonly email: string,
readonly is_active: boolean,
readonly locale: string,
readonly roles: string[],
readonly created_at: string,
};
namespace Auth {
export type MeData = {
readonly userId: string,
readonly activeTenantId: string,
readonly permissions: App.Enums.PermissionEnum[],
};
}
namespace Clients {
export type ClientContactData = {
readonly id: string | null,
readonly name: string,
readonly position: string | null,
readonly email: string | null,
readonly phone: string | null,
readonly is_primary: boolean,
};
export type ClientDetailData = {
readonly id: string,
readonly type: App.Enums.ClientTypeEnum,
readonly name: string,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly is_vat_payer: boolean,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly note: string | null,
readonly contacts: App.Data.Clients.ClientContactData[],
readonly created_at: string,
};
export type ClientListItemData = {
readonly id: string,
readonly type: App.Enums.ClientTypeEnum,
readonly name: string,
readonly ico: string | null,
readonly city: string | null,
readonly contacts_count: number,
readonly objects_count: number,
readonly primary_contact_email: string | null,
readonly primary_contact_phone: string | null,
readonly created_at: string,
};
export type ClientOptionData = {
readonly id: string,
readonly name: string,
};
export type ClientUpsertData = {
readonly type: App.Enums.ClientTypeEnum,
readonly name: string,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly is_vat_payer: boolean,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly note: string | null,
readonly contacts: App.Data.Clients.ClientContactData[],
};
}
namespace ContractTemplates {
export type ContractTemplateDetailData = {
readonly id: string,
readonly name: string,
readonly category: App.Enums.ContractCategoryEnum,
readonly body: string,
readonly is_active: boolean,
};
export type ContractTemplateListItemData = {
readonly id: string,
readonly name: string,
readonly category: App.Enums.ContractCategoryEnum,
readonly is_active: boolean,
readonly updated_at: string,
};
export type ContractTemplateOptionData = {
readonly id: string,
readonly name: string,
readonly category: App.Enums.ContractCategoryEnum,
readonly body: string,
};
export type ContractTemplateUpsertData = {
readonly name: string,
readonly category: App.Enums.ContractCategoryEnum,
readonly body: string,
readonly is_active: boolean,
};
}
namespace Contracts {
export type ContractDetailData = {
readonly id: string,
readonly title: string,
readonly number: string | null,
readonly category: App.Enums.ContractCategoryEnum,
readonly status: App.Enums.ContractStatusEnum,
readonly term_type: App.Enums.ContractTermTypeEnum,
readonly body: string,
readonly valid_from: string,
readonly end_date: string | null,
readonly signed_at: string | null,
readonly terminated_at: string | null,
readonly termination_reason: string | null,
readonly notes: string | null,
readonly contractable_type: App.Enums.ContractableTypeEnum,
readonly contractable_id: string,
readonly contractable_label: string,
readonly contract_template_id: string | null,
readonly contract_template_name: string | null,
readonly quote_id: string | null,
readonly quote_number: string | null,
readonly employment: App.Data.Contracts.EmploymentContractData | null,
readonly is_editable: boolean,
readonly can_be_signed: boolean,
readonly can_be_terminated: boolean,
};
export type ContractFormContextData = {
readonly objects: App.Data.Objects.ObjectOptionData[],
readonly memberships: App.Data.Contracts.MembershipOptionData[],
readonly templates: App.Data.ContractTemplates.ContractTemplateOptionData[],
readonly tokens: App.Data.Contracts.PlaceholderCatalogData,
};
export type ContractListItemData = {
readonly id: string,
readonly title: string,
readonly number: string | null,
readonly category: App.Enums.ContractCategoryEnum,
readonly status: App.Enums.ContractStatusEnum,
readonly term_type: App.Enums.ContractTermTypeEnum,
readonly valid_from: string,
readonly end_date: string | null,
readonly contractable_type: App.Enums.ContractableTypeEnum,
readonly contractable_label: string,
readonly signed_at: string | null,
readonly is_editable: boolean,
readonly can_be_signed: boolean,
readonly can_be_terminated: boolean,
};
export type ContractTerminateData = {
readonly terminated_at: string,
readonly termination_reason: string | null,
};
export type ContractUpsertData = {
readonly title: string,
readonly number: string | null,
readonly category: App.Enums.ContractCategoryEnum,
readonly term_type: App.Enums.ContractTermTypeEnum,
readonly contractable_type: App.Enums.ContractableTypeEnum,
readonly contractable_id: string,
readonly contract_template_id: string | null,
readonly body: string,
readonly valid_from: string,
readonly end_date: string | null,
readonly notes: string | null,
readonly employment: App.Data.Contracts.EmploymentContractUpsertData | null,
};
export type EmploymentContractData = {
readonly employment_type: App.Enums.EmploymentContractTypeEnum,
readonly position: string | null,
readonly hourly_rate: string | null,
readonly monthly_salary: string | null,
readonly weekly_hours: string | null,
readonly probation_end_date: string | null,
};
export type EmploymentContractUpsertData = {
readonly employment_type: App.Enums.EmploymentContractTypeEnum,
readonly position: string | null,
readonly hourly_rate: number | null,
readonly monthly_salary: number | null,
readonly weekly_hours: number | null,
readonly probation_end_date: string | null,
};
export type MembershipOptionData = {
readonly id: string,
readonly label: string,
readonly is_active: boolean,
};
export type PlaceholderCatalogData = {
readonly cleaning_object: App.Data.Contracts.PlaceholderTokenData[],
readonly tenant_membership: App.Data.Contracts.PlaceholderTokenData[],
};
export type PlaceholderTokenData = {
readonly token: string,
readonly label: string,
};
}
namespace Invitations {
export type AcceptInvitationData = {
readonly password: string,
readonly name: string | null,
};
export type InvitationAcceptPageData = {
readonly state: App.Enums.InvitationAcceptStateEnum,
readonly token: string,
readonly email: string | null,
readonly tenant_name: string | null,
readonly role_name: string | null,
readonly invited_email: string | null,
};
}
namespace Invoices {
export type InvoiceDefaultsData = {
readonly constant_symbol: string | null,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
};
export type InvoiceDetailData = {
readonly id: string,
readonly client_id: string | null,
readonly client_name: string | null,
readonly cleaning_object_id: string | null,
readonly credited_invoice_id: string | null,
readonly credit_note_id: string | null,
readonly recurring_invoice_id: string | null,
readonly quote_id: string | null,
readonly quote_number: string | null,
readonly type: App.Enums.InvoiceTypeEnum,
readonly status: App.Enums.InvoiceStatusEnum,
readonly template: App.Enums.InvoiceTemplateEnum,
readonly number: string | null,
readonly variable_symbol: string | null,
readonly period_from: string | null,
readonly period_to: string | null,
readonly issue_date: string,
readonly delivery_date: string,
readonly due_date: string,
readonly issued_at: string | null,
readonly sent_at: string | null,
readonly paid_at: string | null,
readonly cancelled_at: string | null,
readonly is_vat_payer: boolean,
readonly vat_rate: string | null,
readonly subtotal: string,
readonly vat_amount: string,
readonly total: string,
readonly deposit: string,
readonly balance_due: string,
readonly rounding_amount: string,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
readonly constant_symbol: string | null,
readonly specific_symbol: string | null,
readonly header_text: string | null,
readonly footer_text: string | null,
readonly customer_name: string,
readonly customer_representative: string | null,
readonly customer_ico: string | null,
readonly customer_dic: string | null,
readonly customer_vat_number: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly customer_country: string | null,
readonly customer_email: string | null,
readonly object_name: string | null,
readonly object_street: string | null,
readonly object_city: string | null,
readonly object_postal_code: string | null,
readonly note: string | null,
readonly supplier: App.Data.Invoices.InvoiceSupplierData,
readonly items: App.Data.Invoices.InvoiceItemData[],
readonly vat_breakdown: App.Data.Invoices.VatBreakdownLineData[],
readonly qr_available: boolean,
readonly qr_data_uri: string | null,
readonly supplier_missing_fields: string[],
};
export type InvoiceFormContextData = {
readonly clients: App.Data.Clients.ClientOptionData[],
readonly objects: App.Data.Objects.ObjectOptionData[],
readonly is_vat_payer: boolean,
readonly vat_rate: string | null,
readonly vat_rate_options: number[],
readonly defaults: App.Data.Invoices.InvoiceDefaultsData,
readonly recurring_default_state: App.Enums.RecurringDefaultStateEnum,
readonly supplier_missing_fields: string[],
};
export type InvoiceIssueData = {
readonly number: string | null,
};
export type InvoiceItemData = {
readonly id: string | null,
readonly description: string,
readonly quantity: number,
readonly unit: string | null,
readonly unit_price: number,
readonly discount_percent: number,
readonly vat_rate: number,
readonly line_base: number | null,
readonly line_vat: number | null,
readonly line_total: number | null,
};
export type InvoiceListItemData = {
readonly id: string,
readonly number: string | null,
readonly status: App.Enums.InvoiceStatusEnum,
readonly type: App.Enums.InvoiceTypeEnum,
readonly customer_name: string,
readonly client_id: string | null,
readonly client_name: string | null,
readonly object_name: string | null,
readonly currency: App.Enums.CurrencyEnum,
readonly total: string,
readonly balance_due: string,
readonly issue_date: string,
readonly due_date: string,
readonly is_credit_note: boolean,
};
export type InvoiceSettingsData = {
readonly name: string,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly is_vat_payer: boolean,
readonly address_line: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly contact_email: string | null,
readonly contact_phone: string | null,
readonly invoice_template: App.Enums.InvoiceTemplateEnum,
readonly invoice_number_format: string,
readonly iban: string | null,
readonly vat_rate: number | null,
readonly registration_info: string | null,
readonly recurring_default_state: App.Enums.RecurringDefaultStateEnum,
readonly swift_bic: string | null,
readonly default_constant_symbol: string | null,
readonly default_payment_type: App.Enums.PaymentTypeEnum,
readonly default_currency: App.Enums.CurrencyEnum,
readonly default_rounding_mode: App.Enums.RoundingModeEnum,
};
export type InvoiceStatCardData = {
readonly amount: string,
readonly count: number,
};
export type InvoiceStatsData = {
readonly issued_this_month: App.Data.Invoices.InvoiceStatCardData,
readonly overdue: App.Data.Invoices.InvoiceStatCardData,
readonly pending: App.Data.Invoices.InvoiceStatCardData,
readonly recurring_monthly: App.Data.Invoices.InvoiceStatCardData,
readonly currency: App.Enums.CurrencyEnum,
};
export type InvoiceSupplierData = {
readonly name: string,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly iban: string | null,
readonly swift: string | null,
readonly address_line: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string | null,
readonly contact_email: string | null,
readonly contact_phone: string | null,
readonly registration_info: string | null,
};
export type InvoiceUpsertData = {
readonly client_id: string | null,
readonly cleaning_object_id: string | null,
readonly type: App.Enums.InvoiceTypeEnum,
readonly template: App.Enums.InvoiceTemplateEnum | null,
readonly issue_date: string,
readonly delivery_date: string,
readonly due_date: string,
readonly period_from: string | null,
readonly period_to: string | null,
readonly customer_name: string | null,
readonly customer_representative: string | null,
readonly customer_ico: string | null,
readonly customer_dic: string | null,
readonly customer_vat_number: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly customer_country: string | null,
readonly customer_email: string | null,
readonly note: string | null,
readonly items: App.Data.Invoices.InvoiceItemData[],
readonly constant_symbol: string | null,
readonly specific_symbol: string | null,
readonly header_text: string | null,
readonly footer_text: string | null,
readonly deposit: number,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
};
export type VatBreakdownLineData = {
readonly rate: number,
readonly base: number,
readonly vat: number,
readonly total: number,
};
}
namespace Objects {
export type ObjectDetailData = {
readonly id: string,
readonly client_id: string,
readonly client_name: string | null,
readonly type: App.Enums.ObjectTypeEnum,
readonly name: string,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly access_code: string | null,
readonly key_box_code: string | null,
readonly key_count: number | null,
readonly special_instructions: string | null,
readonly area_sqm: string | null,
readonly floor: number | null,
readonly is_active: boolean,
readonly created_at: string,
};
export type ObjectListItemData = {
readonly id: string,
readonly type: App.Enums.ObjectTypeEnum,
readonly name: string,
readonly city: string | null,
readonly is_active: boolean,
readonly client_id: string,
readonly client_name: string | null,
readonly area_sqm: string | null,
readonly created_at: string,
};
export type ObjectOptionData = {
readonly id: string,
readonly name: string,
readonly client_id: string,
readonly client_name: string | null,
readonly is_active: boolean,
};
export type ObjectUpsertData = {
readonly client_id: string,
readonly type: App.Enums.ObjectTypeEnum,
readonly name: string,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly access_code: string | null,
readonly key_box_code: string | null,
readonly key_count: number | null,
readonly special_instructions: string | null,
readonly area_sqm: number | null,
readonly floor: number | null,
readonly is_active: boolean,
};
}
namespace Quotes {
export type QuoteAttachClientData = {
readonly client_id: string,
readonly cleaning_object_id: string | null,
};
export type QuoteContractLinkData = {
readonly id: string,
readonly title: string,
readonly number: string | null,
readonly status: App.Enums.ContractStatusEnum,
};
export type QuoteConvertToContractData = {
readonly contract_template_id: string | null,
};
export type QuoteDetailData = {
readonly id: string,
readonly client_id: string | null,
readonly client_name: string | null,
readonly cleaning_object_id: string | null,
readonly object_name: string | null,
readonly status: App.Enums.QuoteStatusEnum,
readonly kind: App.Enums.QuoteKindEnum,
readonly number: string | null,
readonly subject: string | null,
readonly issue_date: string,
readonly valid_until: string,
readonly sent_at: string | null,
readonly accepted_at: string | null,
readonly rejected_at: string | null,
readonly is_vat_payer: boolean,
readonly vat_rate: string | null,
readonly currency: App.Enums.CurrencyEnum,
readonly subtotal: string,
readonly vat_amount: string,
readonly total: string,
readonly note: string | null,
readonly customer_name: string,
readonly customer_email: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly items: App.Data.Quotes.QuoteItemData[],
readonly vat_breakdown: App.Data.Invoices.VatBreakdownLineData[],
readonly document: App.Data.MediaFileData | null,
readonly invoices: App.Data.Quotes.QuoteInvoiceLinkData[],
readonly contracts: App.Data.Quotes.QuoteContractLinkData[],
};
export type QuoteFormContextData = {
readonly clients: App.Data.Clients.ClientOptionData[],
readonly objects: App.Data.Objects.ObjectOptionData[],
readonly is_vat_payer: boolean,
readonly vat_rate: string | null,
readonly vat_rate_options: number[],
readonly default_validity_days: number,
readonly document_allowed_mimes: string[],
readonly document_max_size_kb: number,
readonly default_currency: App.Enums.CurrencyEnum,
};
export type QuoteInvoiceLinkData = {
readonly id: string,
readonly number: string | null,
readonly status: App.Enums.InvoiceStatusEnum,
};
export type QuoteItemData = {
readonly id: string | null,
readonly description: string,
readonly frequency: string | null,
readonly quantity: number,
readonly unit: string | null,
readonly unit_price: number,
readonly discount_percent: number,
readonly vat_rate: number,
readonly note: string | null,
readonly line_base: number | null,
readonly line_vat: number | null,
readonly line_total: number | null,
};
export type QuoteListItemData = {
readonly id: string,
readonly number: string | null,
readonly status: App.Enums.QuoteStatusEnum,
readonly kind: App.Enums.QuoteKindEnum,
readonly subject: string | null,
readonly customer_name: string,
readonly client_id: string | null,
readonly object_name: string | null,
readonly currency: App.Enums.CurrencyEnum,
readonly total: string,
readonly issue_date: string,
readonly valid_until: string,
readonly has_document: boolean,
};
export type QuoteUpsertData = {
readonly client_id: string | null,
readonly cleaning_object_id: string | null,
readonly subject: string | null,
readonly issue_date: string,
readonly valid_until: string,
readonly note: string | null,
readonly items: App.Data.Quotes.QuoteItemData[],
readonly customer_name: string | null,
readonly customer_email: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly number: string | null,
readonly document_uuid: string | null,
readonly kind: App.Enums.QuoteKindEnum,
readonly currency: App.Enums.CurrencyEnum,
};
}
namespace RecurringInvoices {
export type RecurringInvoiceDetailData = {
readonly id: string,
readonly name: string,
readonly status: App.Enums.RecurringInvoiceStatusEnum,
readonly frequency: App.Enums.RecurringFrequencyEnum,
readonly client_id: string | null,
readonly customer_name: string | null,
readonly customer_display_name: string | null,
readonly day_of_month: number,
readonly next_run_at: string | null,
readonly occurrences_generated: number,
readonly occurrences_limit: number | null,
readonly auto_issue: boolean,
readonly start_date: string,
readonly end_date: string | null,
readonly type: App.Enums.InvoiceTypeEnum,
readonly template: App.Enums.InvoiceTemplateEnum | null,
readonly cleaning_object_id: string | null,
readonly last_generated_at: string | null,
readonly due_days: number,
readonly period_from: string | null,
readonly period_to: string | null,
readonly customer_representative: string | null,
readonly customer_ico: string | null,
readonly customer_dic: string | null,
readonly customer_vat_number: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly customer_country: string | null,
readonly customer_email: string | null,
readonly note: string | null,
readonly is_vat_payer: boolean,
readonly deposit: string,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
readonly constant_symbol: string | null,
readonly header_text: string | null,
readonly footer_text: string | null,
readonly items: App.Data.RecurringInvoices.RecurringInvoiceItemData[],
};
export type RecurringInvoiceItemData = {
readonly description: string,
readonly quantity: number,
readonly unit: string | null,
readonly unit_price: number,
readonly discount_percent: number,
readonly vat_rate: number,
};
export type RecurringInvoiceListItemData = {
readonly id: string,
readonly name: string,
readonly status: App.Enums.RecurringInvoiceStatusEnum,
readonly frequency: App.Enums.RecurringFrequencyEnum,
readonly client_id: string | null,
readonly customer_name: string | null,
readonly customer_display_name: string | null,
readonly day_of_month: number,
readonly next_run_at: string | null,
readonly occurrences_generated: number,
readonly occurrences_limit: number | null,
readonly auto_issue: boolean,
readonly start_date: string,
readonly end_date: string | null,
};
export type RecurringInvoiceUpsertData = {
readonly client_id: string | null,
readonly cleaning_object_id: string | null,
readonly name: string,
readonly type: App.Enums.InvoiceTypeEnum,
readonly template: App.Enums.InvoiceTemplateEnum | null,
readonly frequency: App.Enums.RecurringFrequencyEnum,
readonly day_of_month: number,
readonly auto_issue: boolean,
readonly start_date: string,
readonly end_date: string | null,
readonly occurrences_limit: number | null,
readonly due_days: number,
readonly period_from: string | null,
readonly period_to: string | null,
readonly customer_name: string | null,
readonly customer_representative: string | null,
readonly customer_ico: string | null,
readonly customer_dic: string | null,
readonly customer_vat_number: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly customer_country: string | null,
readonly customer_email: string | null,
readonly note: string | null,
readonly items: App.Data.RecurringInvoices.RecurringInvoiceItemData[],
readonly constant_symbol: string | null,
readonly header_text: string | null,
readonly footer_text: string | null,
readonly deposit: number,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
};
}
namespace Tenants {
export type AddTenantData = {
readonly name: string,
readonly ico: string,
readonly color: App.Enums.TenantColorEnum | null,
readonly copy_settings: boolean,
readonly leader_email: string | null,
};
export type TenantListItemData = {
readonly id: string,
readonly name: string,
readonly is_active: boolean,
readonly color: App.Enums.TenantColorEnum | null,
};
export type TenantSupplierProfileData = {
readonly address_line: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly dic: string | null,
readonly vat_number: string | null,
readonly is_vat_payer: boolean,
readonly contact_email: string | null,
readonly contact_phone: string | null,
readonly iban: string | null,
readonly swift_bic: string | null,
};
}
}
namespace Enums {
export type ClientTypeEnum = "corporate" | "private";
export type ContractCategoryEnum = "service_agreement" | "employment" | "nda" | "gdpr" | "other";
export type ContractStatusEnum = "draft" | "active" | "expired" | "terminated";
export type ContractTermTypeEnum = "fixed" | "indefinite";
export type ContractableTypeEnum = "cleaning_object" | "tenant_membership";
export type CurrencyEnum = "EUR" | "CZK" | "USD";
export type EmploymentContractTypeEnum = "dpp" | "dpc" | "tpp" | "self_employed";
export type InvitationAcceptStateEnum = "expired" | "wrong_user" | "existing_user" | "new_user";
export type InvitationStatusEnum = "pending" | "accepted" | "revoked" | "expired";
export type InvoiceStatusEnum = "draft" | "issued" | "paid" | "overdue" | "cancelled";
export type InvoiceTemplateEnum = "classic" | "modern" | "minimal";
export type InvoiceTypeEnum = "monthly" | "one_off" | "special";
export type ObjectTypeEnum = "office" | "apartment" | "house" | "common_areas";
export type PaymentTypeEnum = "transfer" | "cash" | "card" | "cod" | "other";
export type PermissionEnum = "view employees" | "create employees" | "edit employees" | "delete employees" | "assign employees" | "view roles" | "create roles" | "edit roles" | "delete roles" | "view audit logs" | "view api docs" | "view media" | "upload files" | "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view all objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "delete quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "delete contracts" | "view contract_templates" | "create contract_templates" | "edit contract_templates" | "delete contract_templates" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view all schedule" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "manage billing settings" | "view recurring_invoices" | "create recurring_invoices" | "edit recurring_invoices" | "delete recurring_invoices" | "view notifications" | "configure notifications";
export type QuoteKindEnum = "itemized" | "document";
export type QuoteStatusEnum = "draft" | "sent" | "accepted" | "rejected" | "expired";
export type RecurringDefaultStateEnum = "draft" | "issued";
export type RecurringFrequencyEnum = "monthly" | "every_2_months" | "quarterly" | "semi_annually" | "annually";
export type RecurringInvoiceStatusEnum = "active" | "paused" | "completed" | "cancelled";
export type RoundingModeEnum = "none" | "document" | "cash_005";
export type SupportedLanguage = "sk" | "en" | "uk";
export type TenantColorEnum = "#A16207" | "#D97706" | "#2563EB" | "#4F46E5" | "#0D9488" | "#059669" | "#7C3AED" | "#475569";
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
