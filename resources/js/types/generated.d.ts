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
namespace Invitations {
export type AcceptInvitationData = {
password: string,
name: string | null,
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
export type FeatureEnum = "clients" | "objects" | "quotes" | "contracts" | "schedule" | "invoices" | "employees" | "reports" | "mobile_access" | "multi_user";
export type InvitationStatusEnum = "pending" | "accepted" | "revoked" | "expired";
export type ObjectTypeEnum = "office" | "apartment" | "house" | "common_areas";
export type PermissionEnum = "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "view employees" | "create employees" | "edit employees" | "assign employees" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "view templates" | "upload templates" | "delete templates" | "view complaints" | "resolve complaints" | "reject complaints" | "view photos" | "review photos" | "view notifications" | "configure notifications" | "manage roles" | "manage tenant" | "manage billing settings" | "manage subscription" | "view tenants" | "create tenants" | "edit tenants" | "view audit logs";
export type SubscriptionPlanEnum = "free" | "starter" | "pro" | "enterprise";
export type SupportedLanguage = "sk" | "en" | "uk";
export type TenantColorEnum = "#A16207" | "#D97706" | "#2563EB" | "#4F46E5" | "#0D9488" | "#059669" | "#7C3AED" | "#475569";
}
}
