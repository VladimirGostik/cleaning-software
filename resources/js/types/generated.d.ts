declare namespace App {
namespace Data {
namespace Auth {
export type ForgotPasswordData = {
email: string,
};
export type LoginData = {
email: string,
password: string,
remember: boolean,
};
export type MeData = {
userId: string,
activeTenantId: string | null,
permissions: string[],
features: string[],
accountPlan: string,
remainingTenantSlots: number | null,
};
export type RegisterData = {
name: string,
email: string,
password: string,
terms_accepted: boolean,
company: App.Data.Tenants.CompanyData,
invites: App.Data.Tenants.InviteData[],
};
export type ResetPasswordData = {
token: string,
email: string,
password: string,
password_confirmation: string,
};
}
namespace Clients {
export type ClientContactData = {
id: string | null,
name: string,
position: string | null,
email: string | null,
phone: string | null,
is_primary: boolean,
};
export type ClientDetailData = {
id: string,
type: App.Enums.ClientTypeEnum,
name: string,
ico: string | null,
dic: string | null,
vat_number: string | null,
is_vat_payer: boolean,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
note: string | null,
contacts: App.Data.Clients.ClientContactData[],
objects: any[],
contracts: any[],
invoices: any[],
created_at: string,
updated_at: string,
};
export type ClientIndexFilterData = {
search: string | null,
type: App.Enums.ClientTypeEnum | null,
sort: string,
page: number,
per_page: number,
};
export type ClientListItemData = {
id: string,
type: App.Enums.ClientTypeEnum,
name: string,
ico: string | null,
city: string | null,
contacts_count: number,
objects_count: number,
active_contracts_count: number,
primary_contact_email: string | null,
primary_contact_phone: string | null,
created_at: string,
};
export type ClientOptionData = {
id: string,
name: string,
};
export type ClientStoreData = {
type: App.Enums.ClientTypeEnum,
name: string,
ico: string | null,
dic: string | null,
vat_number: string | null,
is_vat_payer: boolean,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
note: string | null,
contacts: App.Data.Clients.ClientContactData[],
};
export type ClientUpdateData = {
type: App.Enums.ClientTypeEnum,
name: string,
ico: string | null,
dic: string | null,
vat_number: string | null,
is_vat_payer: boolean,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
note: string | null,
contacts: App.Data.Clients.ClientContactData[],
};
}
namespace ContractTemplates {
export type ContractTemplateDetailData = {
id: string,
name: string,
category: App.Enums.ContractCategoryEnum,
body: string,
is_active: boolean,
created_at: string,
updated_at: string,
};
export type ContractTemplateIndexFilterData = {
search: string | null,
category: string | null,
is_active: boolean | null,
per_page: number,
};
export type ContractTemplateListItemData = {
id: string,
name: string,
body: string,
category: App.Enums.ContractCategoryEnum,
is_active: boolean,
created_at: string,
};
export type ContractTemplateStoreData = {
name: string,
category: App.Enums.ContractCategoryEnum,
body: string,
is_active: boolean,
};
}
namespace Contracts {
export type ContractDetailData = {
id: string,
title: string,
reference_number: string | null,
category: App.Enums.ContractCategoryEnum,
status: App.Enums.ContractStatusEnum,
term_type: App.Enums.ContractTermTypeEnum,
body: string,
valid_from: string,
end_date: string | null,
signed_at: string | null,
terminated_at: string | null,
termination_reason: string | null,
notes: string | null,
contractable_id: string,
contractable_type: string,
contractable_display_name: string,
contract_template_id: string | null,
contract_template_name: string | null,
employment: App.Data.Contracts.EmploymentContractData | null,
is_editable: boolean,
can_be_signed: boolean,
can_be_terminated: boolean,
};
export type ContractIndexFilterData = {
search: string | null,
status: string | null,
category: string | null,
term_type: string | null,
contractable_type: string | null,
per_page: number,
};
export type ContractListItemData = {
id: string,
title: string,
reference_number: string | null,
category: App.Enums.ContractCategoryEnum,
status: App.Enums.ContractStatusEnum,
term_type: App.Enums.ContractTermTypeEnum,
valid_from: string,
end_date: string | null,
contractable_type: string,
contractable_display_name: string,
signed_at: string | null,
terminated_at: string | null,
};
export type ContractTerminateData = {
terminated_at: string,
termination_reason: string | null,
};
export type ContractUpsertData = {
title: string,
reference_number: string | null,
category: App.Enums.ContractCategoryEnum,
term_type: App.Enums.ContractTermTypeEnum,
contractable_type: string,
contractable_id: string,
contract_template_id: string | null,
body: string,
valid_from: string,
end_date: string | null,
notes: string | null,
employment: App.Data.Contracts.EmploymentContractUpsertData | null,
};
export type EmploymentContractData = {
employment_type: App.Enums.EmploymentContractTypeEnum,
position: string | null,
hourly_rate: string | null,
monthly_salary: string | null,
weekly_hours: string | null,
probation_end_date: string | null,
};
export type EmploymentContractUpsertData = {
employment_type: App.Enums.EmploymentContractTypeEnum,
position: string | null,
hourly_rate: number | null,
monthly_salary: number | null,
weekly_hours: number | null,
probation_end_date: string | null,
};
export type MembershipOptionData = {
id: string,
user_name: string,
user_email: string,
is_active: boolean,
};
}
namespace Employees {
export type EmployeeDetailData = {
id: string,
user_id: string,
user_email: string,
user_name: string,
role_name: string | null,
permissions: string[],
first_name: string | null,
last_name: string | null,
phone: string | null,
position: string | null,
display_name: string,
employment_contract: App.Data.Contracts.EmploymentContractData | null,
other_tenants_count: number,
assigned_objects: any[],
is_active: boolean,
joined_at: string,
};
export type EmployeeIndexFilterData = {
search: string | null,
role: string | null,
is_active: boolean | null,
sort: string,
page: number,
per_page: number,
};
export type EmployeeListItemData = {
id: string,
display_name: string,
email: string,
phone: string | null,
role_name: string | null,
assigned_objects_count: number,
employment_type: string | null,
is_active: boolean,
};
export type EmployeeUpsertData = {
email: string,
first_name: string | null,
last_name: string | null,
phone: string | null,
role_name: string,
permissions: string[],
employment: App.Data.Contracts.EmploymentContractUpsertData | null,
};
}
namespace Invitations {
export type AcceptInvitationData = {
password: string,
name: string | null,
};
}
namespace Invoices {
export type BulkInvoiceData = {
action: string,
ids: string[],
};
export type InvoiceDetailData = {
id: string,
client_id: string | null,
cleaning_object_id: string | null,
credited_invoice_id: string | null,
type: App.Enums.InvoiceTypeEnum,
status: App.Enums.InvoiceStatusEnum,
template: App.Enums.InvoiceTemplateEnum,
number: string | null,
variable_symbol: string | null,
period_from: string | null,
period_to: string | null,
issue_date: string,
delivery_date: string,
due_date: string,
issued_at: string | null,
sent_at: string | null,
paid_at: string | null,
cancelled_at: string | null,
is_vat_payer: boolean,
vat_rate: string | null,
subtotal: string,
vat_amount: string,
total: string,
deposit: string,
balance_due: string,
rounding_amount: string,
payment_type: App.Enums.PaymentTypeEnum,
currency: App.Enums.CurrencyEnum,
rounding_mode: App.Enums.RoundingModeEnum,
constant_symbol: string | null,
specific_symbol: string | null,
header_text: string | null,
footer_text: string | null,
customer_name: string,
customer_representative: string | null,
customer_ico: string | null,
customer_dic: string | null,
customer_vat_number: string | null,
customer_street: string | null,
customer_city: string | null,
customer_postal_code: string | null,
customer_country: string | null,
customer_email: string | null,
object_name: string | null,
object_street: string | null,
object_city: string | null,
object_postal_code: string | null,
note: string | null,
supplier: App.Data.Invoices.InvoiceSupplierData,
items: App.Data.Invoices.InvoiceItemData[],
vat_breakdown: App.Data.Invoices.VatBreakdownLineData[],
qr_available: boolean,
qr_data_uri: string | null,
};
export type InvoiceIndexFilterData = {
search: string | null,
status: App.Enums.InvoiceStatusEnum | null,
type: App.Enums.InvoiceTypeEnum | null,
client_id: string | null,
tab: string | null,
month: string | null,
issued_from: string | null,
issued_to: string | null,
due_from: string | null,
due_to: string | null,
total_min: string | null,
total_max: string | null,
per_page: number,
};
export type InvoiceIssueData = {
number: string | null,
};
export type InvoiceItemData = {
id: string | null,
description: string,
quantity: number,
unit: string | null,
unit_price: number,
discount_percent: number,
vat_rate: number,
line_base: number | null,
line_vat: number | null,
line_total: number | null,
};
export type InvoiceListItemData = {
id: string,
number: string | null,
status: App.Enums.InvoiceStatusEnum,
type: App.Enums.InvoiceTypeEnum,
customer_name: string,
object_name: string | null,
total: string,
issue_date: string,
due_date: string,
client_id: string | null,
};
export type InvoiceSettingsData = {
invoice_template: App.Enums.InvoiceTemplateEnum,
invoice_number_format: string,
iban: string | null,
vat_rate: number | null,
registration_info: string | null,
recurring_default_state: App.Enums.RecurringDefaultStateEnum,
swift_bic: string | null,
default_constant_symbol: string | null,
default_payment_type: App.Enums.PaymentTypeEnum,
default_currency: App.Enums.CurrencyEnum,
default_rounding_mode: App.Enums.RoundingModeEnum,
};
export type InvoiceStatCardData = {
amount: string,
count: number,
};
export type InvoiceStatsData = {
issued_this_month: App.Data.Invoices.InvoiceStatCardData,
overdue: App.Data.Invoices.InvoiceStatCardData,
pending: App.Data.Invoices.InvoiceStatCardData,
recurring_monthly: App.Data.Invoices.InvoiceStatCardData,
};
export type InvoiceSupplierData = {
name: string,
ico: string | null,
dic: string | null,
vat_number: string | null,
iban: string | null,
swift: string | null,
address_line: string | null,
city: string | null,
postal_code: string | null,
country: string | null,
contact_email: string | null,
contact_phone: string | null,
registration_info: string | null,
};
export type InvoiceUpsertData = {
client_id: string | null,
cleaning_object_id: string | null,
type: App.Enums.InvoiceTypeEnum,
template: App.Enums.InvoiceTemplateEnum | null,
issue_date: string,
delivery_date: string,
due_date: string,
period_from: string | null,
period_to: string | null,
customer_name: string | null,
customer_representative: string | null,
customer_ico: string | null,
customer_dic: string | null,
customer_vat_number: string | null,
customer_street: string | null,
customer_city: string | null,
customer_postal_code: string | null,
customer_country: string | null,
customer_email: string | null,
note: string | null,
items: App.Data.Invoices.InvoiceItemData[],
constant_symbol: string | null,
specific_symbol: string | null,
header_text: string | null,
footer_text: string | null,
deposit: number,
payment_type: App.Enums.PaymentTypeEnum,
currency: App.Enums.CurrencyEnum,
rounding_mode: App.Enums.RoundingModeEnum,
};
export type TabCountsData = {
readonly all: number | null,
readonly all_issued: number,
readonly recurring: number,
readonly drafts: number,
readonly overdue: number,
};
export type VatBreakdownLineData = {
rate: number,
base: number,
vat: number,
total: number,
};
}
namespace Notifications {
export type NotificationBellData = {
unreadCount: number,
recent: App.Data.Notifications.NotificationListItemData[],
};
export type NotificationIndexFilterData = {
unreadOnly: boolean | null,
type: string | null,
perPage: number,
};
export type NotificationListItemData = {
id: string,
type: string,
title: string,
body: string,
url: string | null,
readAt: string | null,
createdAt: string,
};
export type NotificationPreferenceItemData = {
type: string,
label: string,
mail: boolean,
configurable: boolean,
};
export type NotificationPreferencesData = {
items: App.Data.Notifications.NotificationPreferenceItemData[],
};
export type NotificationPreferencesUpdateData = {
preferences: Record<string, boolean>,
};
}
namespace Objects {
export type ObjectDetailData = {
id: string,
client_id: string,
client_name: string | null,
type: App.Enums.ObjectTypeEnum,
name: string,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
access_code: string | null,
key_box_code: string | null,
key_count: number | null,
special_instructions: string | null,
area_sqm: string | null,
floor: number | null,
is_active: boolean,
created_at: string,
updated_at: string,
};
export type ObjectIndexFilterData = {
search: string | null,
type: App.Enums.ObjectTypeEnum | null,
client_id: string | null,
is_active: boolean | null,
sort: string,
page: number,
per_page: number,
};
export type ObjectListItemData = {
id: string,
type: App.Enums.ObjectTypeEnum,
name: string,
city: string | null,
is_active: boolean,
client_id: string,
client_name: string | null,
area_sqm: string | null,
created_at: string,
};
export type ObjectOptionData = {
id: string,
name: string,
client_id: string,
};
export type ObjectStoreData = {
client_id: string,
type: App.Enums.ObjectTypeEnum,
name: string,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
access_code: string | null,
key_box_code: string | null,
key_count: number | null,
special_instructions: string | null,
area_sqm: number | null,
floor: number | null,
is_active: boolean,
};
export type ObjectUpdateData = {
client_id: string,
type: App.Enums.ObjectTypeEnum,
name: string,
street: string | null,
city: string | null,
postal_code: string | null,
country: string,
access_code: string | null,
key_box_code: string | null,
key_count: number | null,
special_instructions: string | null,
area_sqm: number | null,
floor: number | null,
is_active: boolean,
};
}
namespace Quotes {
export type QuoteDetailData = {
id: string,
client_id: string,
cleaning_object_id: string | null,
status: App.Enums.QuoteStatusEnum,
number: string | null,
subject: string | null,
issue_date: string,
valid_until: string,
sent_at: string | null,
accepted_at: string | null,
rejected_at: string | null,
is_vat_payer: boolean,
vat_rate: string | null,
currency: App.Enums.CurrencyEnum,
subtotal: string,
vat_amount: string,
total: string,
note: string | null,
customer_name: string,
object_name: string | null,
items: App.Data.Quotes.QuoteItemData[],
vat_breakdown: App.Data.Invoices.VatBreakdownLineData[],
};
export type QuoteIndexFilterData = {
search: string | null,
status: App.Enums.QuoteStatusEnum | null,
client_id: string | null,
valid_from: string | null,
valid_to: string | null,
per_page: number,
};
export type QuoteItemData = {
id: string | null,
name: string,
description: string | null,
frequency: string | null,
quantity: number,
unit: string | null,
unit_price: number,
discount_percent: number,
vat_rate: number,
line_base: number | null,
line_vat: number | null,
line_total: number | null,
};
export type QuoteListItemData = {
id: string,
number: string | null,
status: App.Enums.QuoteStatusEnum,
subject: string | null,
customer_name: string,
object_name: string | null,
total: string,
issue_date: string,
valid_until: string,
client_id: string,
};
export type QuoteUpsertData = {
client_id: string,
cleaning_object_id: string | null,
subject: string | null,
issue_date: string,
valid_until: string,
note: string | null,
items: App.Data.Quotes.QuoteItemData[],
currency: App.Enums.CurrencyEnum,
};
}
namespace RecurringInvoices {
export type RecurringInvoiceDetailData = {
id: string,
name: string,
status: App.Enums.RecurringInvoiceStatusEnum,
frequency: App.Enums.RecurringFrequencyEnum,
type: App.Enums.InvoiceTypeEnum,
template: App.Enums.InvoiceTemplateEnum | null,
client_id: string | null,
cleaning_object_id: string | null,
day_of_month: number,
auto_issue: boolean,
start_date: string,
end_date: string | null,
occurrences_limit: number | null,
occurrences_generated: number,
next_run_at: string | null,
last_generated_at: string | null,
due_days: number,
period_from: string | null,
period_to: string | null,
customer_name: string | null,
customer_display_name: string | null,
customer_representative: string | null,
customer_ico: string | null,
customer_dic: string | null,
customer_vat_number: string | null,
customer_street: string | null,
customer_city: string | null,
customer_postal_code: string | null,
customer_country: string | null,
customer_email: string | null,
note: string | null,
deposit: string,
payment_type: App.Enums.PaymentTypeEnum,
currency: App.Enums.CurrencyEnum,
rounding_mode: App.Enums.RoundingModeEnum,
constant_symbol: string | null,
header_text: string | null,
footer_text: string | null,
items: App.Data.RecurringInvoices.RecurringInvoiceItemData[],
};
export type RecurringInvoiceIndexFilterData = {
search: string | null,
status: App.Enums.RecurringInvoiceStatusEnum | null,
frequency: App.Enums.RecurringFrequencyEnum | null,
client_id: string | null,
per_page: number,
};
export type RecurringInvoiceItemData = {
description: string,
quantity: number,
unit: string | null,
unit_price: number,
discount_percent: number,
vat_rate: number,
};
export type RecurringInvoiceListItemData = {
id: string,
name: string,
status: App.Enums.RecurringInvoiceStatusEnum,
frequency: App.Enums.RecurringFrequencyEnum,
client_id: string | null,
customer_name: string | null,
customer_display_name: string | null,
day_of_month: number,
next_run_at: string | null,
occurrences_generated: number,
occurrences_limit: number | null,
auto_issue: boolean,
start_date: string,
end_date: string | null,
};
export type RecurringInvoiceUpsertData = {
client_id: string | null,
cleaning_object_id: string | null,
name: string,
type: App.Enums.InvoiceTypeEnum,
template: App.Enums.InvoiceTemplateEnum | null,
frequency: App.Enums.RecurringFrequencyEnum,
day_of_month: number,
auto_issue: boolean,
start_date: string,
end_date: string | null,
occurrences_limit: number | null,
due_days: number,
period_from: string | null,
period_to: string | null,
customer_name: string | null,
customer_representative: string | null,
customer_ico: string | null,
customer_dic: string | null,
customer_vat_number: string | null,
customer_street: string | null,
customer_city: string | null,
customer_postal_code: string | null,
customer_country: string | null,
customer_email: string | null,
note: string | null,
items: App.Data.RecurringInvoices.RecurringInvoiceItemData[],
constant_symbol: string | null,
header_text: string | null,
footer_text: string | null,
deposit: number,
payment_type: App.Enums.PaymentTypeEnum,
currency: App.Enums.CurrencyEnum,
rounding_mode: App.Enums.RoundingModeEnum,
};
}
namespace Schedule {
export type JobAssignData = {
assigned_membership_id: string | null,
};
export type JobCalendarFilterData = {
date_from: string,
date_to: string,
cleaning_object_id: string | null,
assigned_membership_id: string | null,
};
export type JobDetailData = {
id: string,
cleaning_object_id: string,
object_name: string,
client_name: string,
client_id: string | null,
assigned_membership_id: string | null,
assignee_display_name: string | null,
work_breakdown_id: string | null,
work_breakdown_task_id: string | null,
contract_id: string | null,
invoice_id: string | null,
type: App.Enums.JobTypeEnum,
status: App.Enums.JobStatusEnum,
scheduled_date: string,
start_time: string | null,
end_time: string | null,
note: string | null,
is_invoiced: boolean,
is_editable: boolean,
can_be_cancelled: boolean,
can: Record<string, boolean>,
};
export type JobIndexFilterData = {
search: string | null,
status: App.Enums.JobStatusEnum | null,
type: App.Enums.JobTypeEnum | null,
cleaning_object_id: string | null,
assigned_membership_id: string | null,
date_from: string | null,
date_to: string | null,
per_page: number,
};
export type JobListItemData = {
id: string,
scheduled_date: string,
start_time: string | null,
end_time: string | null,
object_name: string,
client_name: string,
assignee_display_name: string | null,
type: App.Enums.JobTypeEnum,
status: App.Enums.JobStatusEnum,
is_invoiced: boolean,
};
export type JobUpsertData = {
cleaning_object_id: string,
type: App.Enums.JobTypeEnum,
scheduled_date: string,
start_time: string | null,
end_time: string | null,
assigned_membership_id: string | null,
contract_id: string | null,
note: string | null,
};
export type WorkBreakdownDetailData = {
id: string,
cleaning_object_id: string,
name: string,
is_active: boolean,
contract_id: string | null,
source_quote_id: string | null,
tasks: App.Data.Schedule.WorkBreakdownTaskData[],
};
export type WorkBreakdownTaskData = {
id: string,
name: string,
description: string | null,
frequency: App.Enums.TaskFrequencyEnum,
position: number,
};
export type WorkBreakdownUpsertData = {
cleaning_object_id: string,
name: string,
is_active: boolean,
};
}
namespace Tenants {
export type AddTenantData = {
name: string,
ico: string,
color: App.Enums.TenantColorEnum | null,
copy_settings: boolean,
leader_email: string | null,
};
export type CompanyData = {
name: string,
ico: string,
dic: string | null,
vat_number: string | null,
is_vat_payer: boolean,
address_line: string,
city: string,
postal_code: string,
country: string,
};
export type IcoLookupData = {
name: string,
dic: string | null,
vat_number: string | null,
address_line: string,
city: string,
postal_code: string,
};
export type InviteData = {
email: string,
role_name: string,
};
export type TenantListItemData = {
id: string,
name: string,
is_active: boolean,
};
}
}
namespace Enums {
export type ClientTypeEnum = "corporate" | "private";
export type ContractCategoryEnum = "service_agreement" | "employment" | "nda" | "gdpr" | "other";
export type ContractStatusEnum = "draft" | "active" | "expired" | "terminated";
export type ContractTermTypeEnum = "fixed" | "indefinite";
export type CurrencyEnum = "EUR" | "CZK" | "USD";
export type EmploymentContractTypeEnum = "dpp" | "dpc" | "tpp" | "self_employed";
export type FeatureEnum = "clients" | "objects" | "quotes" | "contracts" | "schedule" | "invoices" | "employees" | "reports" | "mobile_access" | "multi_user";
export type InvitationStatusEnum = "pending" | "accepted" | "revoked" | "expired";
export type InvoiceStatusEnum = "draft" | "issued" | "paid" | "overdue" | "cancelled";
export type InvoiceTemplateEnum = "classic" | "modern" | "minimal";
export type InvoiceTypeEnum = "monthly" | "one_off" | "special";
export type JobStatusEnum = "planned" | "unassigned" | "in_progress" | "completed" | "unapproved" | "cancelled";
export type JobTypeEnum = "regular" | "one_off" | "special";
export type NotificationTypeEnum = "invitation.created" | "invoice.issued" | "invoice.overdue" | "contract.expiring" | "contract.expired" | "quote.sent" | "quote.expiring" | "quote.expired";
export type ObjectTypeEnum = "office" | "apartment" | "house" | "common_areas";
export type PaymentTypeEnum = "transfer" | "cash" | "card" | "cod" | "other";
export type PermissionEnum = "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "delete quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "delete contracts" | "view contract_templates" | "create contract_templates" | "edit contract_templates" | "delete contract_templates" | "view employees" | "create employees" | "edit employees" | "assign employees" | "delete employees" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "view recurring_invoices" | "create recurring_invoices" | "edit recurring_invoices" | "delete recurring_invoices" | "view templates" | "upload templates" | "delete templates" | "view complaints" | "resolve complaints" | "reject complaints" | "view photos" | "review photos" | "view notifications" | "configure notifications" | "manage roles" | "manage tenant" | "manage billing settings" | "manage subscription" | "view tenants" | "create tenants" | "edit tenants" | "view audit logs";
export type QuoteStatusEnum = "draft" | "sent" | "accepted" | "rejected" | "expired";
export type RecurringDefaultStateEnum = "draft" | "issued";
export type RecurringFrequencyEnum = "monthly" | "every_2_months" | "quarterly" | "semi_annually" | "annually";
export type RecurringInvoiceStatusEnum = "active" | "paused" | "completed" | "cancelled";
export type RoundingModeEnum = "none" | "document" | "cash_005";
export type SubscriptionPlanEnum = "free" | "starter" | "pro" | "enterprise";
export type SupportedLanguage = "sk" | "en" | "uk";
export type TaskFrequencyEnum = "one_time" | "weekly_1x" | "weekly_2x" | "weekly_3x" | "biweekly" | "monthly" | "bimonthly" | "seasonal";
export type TenantColorEnum = "#A16207" | "#D97706" | "#2563EB" | "#4F46E5" | "#0D9488" | "#059669" | "#7C3AED" | "#475569";
}
}
