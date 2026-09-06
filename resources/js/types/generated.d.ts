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
}
}
namespace Enums {
export type InvitationAcceptStateEnum = "expired" | "wrong_user" | "existing_user" | "new_user";
export type InvitationStatusEnum = "pending" | "accepted" | "revoked" | "expired";
export type PermissionEnum = "view employees" | "create employees" | "edit employees" | "delete employees" | "assign employees" | "view roles" | "create roles" | "edit roles" | "delete roles" | "view audit logs" | "view api docs" | "view media" | "upload files" | "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view all objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "delete quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "delete contracts" | "view contract_templates" | "create contract_templates" | "edit contract_templates" | "delete contract_templates" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view all schedule" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "manage billing settings" | "view recurring_invoices" | "create recurring_invoices" | "edit recurring_invoices" | "delete recurring_invoices" | "view notifications" | "configure notifications";
export type SupportedLanguage = "sk" | "en" | "uk";
export type TenantColorEnum = "#A16207" | "#D97706" | "#2563EB" | "#4F46E5" | "#0D9488" | "#059669" | "#7C3AED" | "#475569";
}
}
