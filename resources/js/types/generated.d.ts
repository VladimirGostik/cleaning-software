declare namespace App {
namespace Data {
namespace Auth {
export type LoginData = {
email: string,
password: string,
remember: boolean,
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
type: App.Enums.ClientType,
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
type: App.Enums.ClientType | null,
sort: string,
page: number,
per_page: number,
};
export type ClientListItemData = {
id: string,
type: App.Enums.ClientType,
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
type: App.Enums.ClientType,
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
type: App.Enums.ClientType,
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
}
namespace Enums {
export type ClientType = "corporate" | "private";
export type SupportedLanguage = "sk" | "en" | "uk";
}
}
