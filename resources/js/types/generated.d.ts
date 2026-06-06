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
namespace Tenants {
export type TenantListItemData = {
id: string,
name: string,
is_active: boolean,
};
}
}
namespace Enums {
export type ClientTypeEnum = "corporate" | "private";
export type FeatureEnum = "clients" | "objects" | "quotes" | "contracts" | "schedule" | "invoices" | "employees" | "reports" | "mobile_access" | "multi_user";
export type PermissionEnum = "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "view employees" | "create employees" | "edit employees" | "assign employees" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "view templates" | "upload templates" | "delete templates" | "view complaints" | "resolve complaints" | "reject complaints" | "view photos" | "review photos" | "view notifications" | "configure notifications" | "manage roles" | "manage tenant" | "manage billing settings" | "manage subscription" | "view tenants" | "create tenants" | "edit tenants" | "view audit logs";
export type SubscriptionPlanEnum = "free" | "starter" | "pro" | "enterprise";
export type SupportedLanguage = "sk" | "en" | "uk";
}
}
