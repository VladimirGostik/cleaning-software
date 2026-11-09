declare namespace App {
namespace Data {
export type ActivityLogDetailData = {
readonly id: number,
readonly log_name: string | null,
readonly description: string,
readonly subject_type: string | null,
readonly subject_id: string | null,
readonly subject_label: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly properties: Record<string, any> | null,
readonly attribute_changes: Record<string, any> | null,
readonly created_at: string,
readonly revert: App.Data.ActivityRevertData,
readonly changes: App.Data.ActivityRevertChangeData[],
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
readonly subject_label: string | null,
readonly event: string | null,
readonly causer_name: string | null,
readonly causer_email: string | null,
readonly created_at: string,
readonly revert: App.Data.ActivityRevertData,
};
export type ActivityRevertChangeData = {
readonly field: string,
readonly label: string,
readonly old: any,
readonly new: any,
readonly current: any,
readonly conflict: boolean,
readonly masked: boolean,
};
export type ActivityRevertData = {
readonly status: App.Enums.ActivityRevertStatusEnum,
readonly reason: App.Enums.ActivityRevertBlockReasonEnum | null,
readonly changes_count: number,
readonly batch_size: number,
readonly blocking_activity_id: number | null,
readonly reverted_at: string | null,
readonly reverted_by_name: string | null,
readonly reverted_by_activity_id: number | null,
readonly reverts_activity_id: number | null,
};
export type AuthTokenData = {
readonly token: string,
readonly refresh_token: string,
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
export type MobileTokenPairData = {
readonly token: string,
readonly refresh_token: string,
};
export type NavigationItemData = {
readonly key: string,
readonly label: string,
readonly href: string,
readonly icon: App.Enums.NavIconEnum,
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
export type RefreshTokenData = {
readonly refresh_token: string,
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
namespace Ai {
export type ActionResultData = {
readonly action: App.Enums.PendingActionEnum,
readonly subject_label: string,
readonly url: string | null,
readonly message: string,
};
export type AssistantActionData = {
readonly type: App.Enums.AssistantActionTypeEnum,
readonly label: string,
readonly url: string | null,
};
export type AssistantMessageData = {
readonly role: App.Enums.AssistantRoleEnum,
readonly content: string,
};
export type AssistantReplyData = {
readonly reply: string,
readonly actions: App.Data.Ai.AssistantActionData[],
readonly pending_action: App.Data.Ai.PendingActionData | null,
readonly security_notice: string | null,
};
export type AssistantRequestData = {
readonly company_id: string,
readonly messages: App.Data.Ai.AssistantMessageData[],
};
export type PendingActionData = {
readonly id: string,
readonly action: App.Enums.PendingActionEnum,
readonly preview: string,
readonly tenant_id: string,
readonly tenant_name: string,
readonly subject_label: string,
readonly customer: string | null,
readonly total: string | null,
readonly email: string | null,
readonly expires_at: string,
readonly confirm_in_app_only: boolean,
readonly status: App.Enums.PendingActionStatusEnum,
};
}
namespace Auth {
export type MeData = {
readonly userId: string,
readonly name: string,
readonly email: string,
readonly locale: App.Enums.SupportedLanguage,
readonly activeTenantId: string,
readonly permissions: App.Enums.PermissionEnum[],
readonly companies: App.Data.Auth.MobileCompanyData[],
};
export type MobileCompanyData = {
readonly tenant_id: string,
readonly name: string,
readonly color: App.Enums.TenantColorEnum | null,
readonly mobile_access: boolean,
readonly permissions: App.Enums.PermissionEnum[],
};
}
namespace Clients {
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
readonly contacts: App.Data.Contacts.ContactData[],
readonly created_at: string,
readonly peppol_participant_id: string | null,
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
readonly contacts: App.Data.Contacts.ContactData[],
readonly peppol_participant_id: string | null,
};
}
namespace Contacts {
export type ContactData = {
readonly id: string | null,
readonly name: string,
readonly position: string | null,
readonly email: string | null,
readonly phone: string | null,
readonly is_primary: boolean,
};
}
namespace Contracts {
export type ContractCreatePrefillData = {
readonly contractable_type: App.Enums.ContractableTypeEnum | null,
readonly contractable_id: string | null,
readonly contractable_label: string | null,
readonly category: App.Enums.ContractCategoryEnum | null,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
readonly document_template_id: string | null,
};
export type ContractDetailData = {
readonly id: string,
readonly title: string,
readonly number: string | null,
readonly category: App.Enums.ContractCategoryEnum,
readonly status: App.Enums.ContractStatusEnum,
readonly term_type: App.Enums.ContractTermTypeEnum,
readonly body: string | null,
readonly show_header: boolean,
readonly show_footer: boolean,
readonly letterhead: App.Data.Documents.DocumentLetterheadData,
readonly footer: string[],
readonly missing_tokens: string[],
readonly tokens: App.Data.DocumentTemplates.DocumentTokenData[],
readonly valid_from: string,
readonly end_date: string | null,
readonly signed_at: string | null,
readonly terminated_at: string | null,
readonly termination_reason: string | null,
readonly notes: string | null,
readonly contractable_type: App.Enums.ContractableTypeEnum,
readonly contractable_id: string,
readonly contractable_label: string,
readonly document_template_id: string | null,
readonly document_template_name: string | null,
readonly quote_id: string | null,
readonly quote_number: string | null,
readonly employment: App.Data.Contracts.EmploymentContractData | null,
readonly is_editable: boolean,
readonly can_be_signed: boolean,
readonly can_be_terminated: boolean,
};
export type ContractFormContextData = {
readonly objects: App.Data.Objects.ObjectOptionData[],
readonly clients: App.Data.Clients.ClientOptionData[],
readonly memberships: App.Data.Contracts.MembershipOptionData[],
readonly templates: App.Data.DocumentTemplates.DocumentTemplateOptionData[],
readonly tokens: App.Data.DocumentTemplates.DocumentTokenCatalogData,
readonly letterhead: App.Data.Documents.DocumentLetterheadData,
readonly footer: string[],
};
export type ContractGenerateData = {
readonly category: App.Enums.ContractCategoryEnum,
readonly term_type: App.Enums.ContractTermTypeEnum,
readonly contractable_type: App.Enums.ContractableTypeEnum,
readonly contractable_id: string,
readonly valid_from: string,
readonly document_template_id: string | null,
readonly body: string | null,
readonly title: string | null,
readonly number: string | null,
readonly end_date: string | null,
readonly employment: App.Data.Contracts.EmploymentContractUpsertData | null,
readonly quote_id: string | null,
readonly show_header: boolean,
readonly show_footer: boolean,
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
readonly document_template_id: string | null,
readonly body: string,
readonly valid_from: string,
readonly end_date: string | null,
readonly notes: string | null,
readonly employment: App.Data.Contracts.EmploymentContractUpsertData | null,
readonly show_header: boolean,
readonly show_footer: boolean,
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
export type PlaceholderTokenData = {
readonly token: string,
readonly label: string,
};
}
namespace Dashboard {
export type CompanyOverviewData = {
readonly tenant_id: string,
readonly name: string,
readonly color: App.Enums.TenantColorEnum | null,
readonly is_current: boolean,
readonly supplier_complete: boolean,
readonly invoices: App.Data.Dashboard.InvoiceMetricsData | null,
readonly schedule: App.Data.Dashboard.ScheduleMetricsData | null,
readonly contracts: App.Data.Dashboard.ContractsMetricsData | null,
readonly people: App.Data.Dashboard.PeopleMetricsData | null,
};
export type ContractsMetricsData = {
readonly active: number | null,
readonly expiring_30d: number | null,
readonly quotes_awaiting: number | null,
};
export type DashboardAlertCountData = {
readonly type: App.Enums.DashboardAlertTypeEnum,
readonly count: number,
};
export type DashboardAlertData = {
readonly type: App.Enums.DashboardAlertTypeEnum,
readonly tenant_id: string,
readonly tenant_name: string,
readonly tenant_color: App.Enums.TenantColorEnum | null,
readonly title: string,
readonly subtitle: string | null,
readonly amount: string | null,
readonly currency: App.Enums.CurrencyEnum | null,
readonly due_date: string | null,
readonly days: number | null,
readonly url: string,
};
export type DashboardData = {
readonly companies: App.Data.Dashboard.CompanyOverviewData[],
readonly alerts: App.Data.Dashboard.DashboardAlertData[],
readonly alert_counts: App.Data.Dashboard.DashboardAlertCountData[],
readonly generated_at: string,
};
export type InvoiceCurrencyMetricsData = {
readonly currency: App.Enums.CurrencyEnum,
readonly invoiced_month_count: number,
readonly invoiced_month_sum: string,
readonly unpaid_count: number,
readonly unpaid_sum: string,
readonly overdue_count: number,
readonly overdue_sum: string,
};
export type InvoiceMetricsData = {
readonly default_currency: App.Enums.CurrencyEnum,
readonly by_currency: App.Data.Dashboard.InvoiceCurrencyMetricsData[],
};
export type PeopleMetricsData = {
readonly employees: number | null,
readonly clients: number | null,
readonly objects: number | null,
};
export type ScheduleMetricsData = {
readonly today: number,
readonly this_week: number,
readonly unassigned_next_7_days: number | null,
readonly own_only: boolean,
};
}
namespace Devices {
export type DeviceRegisterData = {
readonly token: string,
readonly platform: App.Enums.DevicePlatformEnum,
readonly app_version: string | null,
};
export type DeviceUnregisterData = {
readonly token: string,
};
}
namespace DocumentTemplates {
export type DocumentTemplateCanData = {
readonly update: boolean,
readonly delete: boolean,
readonly customize: boolean,
readonly reset: boolean,
readonly acknowledge: boolean,
};
export type DocumentTemplateEditData = {
readonly id: string,
readonly name: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
readonly scope: App.Enums.DocumentTemplateScopeEnum,
readonly state: App.Enums.DocumentTemplateStateEnum | null,
readonly is_active: boolean,
readonly body: string,
readonly base_body: string | null,
readonly previous_base_body: string | null,
readonly show_header: boolean,
readonly show_footer: boolean,
readonly tokens: App.Data.DocumentTemplates.DocumentTokenData[],
readonly letterhead: App.Data.Documents.DocumentLetterheadData,
readonly footer: string[],
readonly can: App.Data.DocumentTemplates.DocumentTemplateCanData,
};
export type DocumentTemplateListItemData = {
readonly id: string,
readonly name: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
readonly scope: App.Enums.DocumentTemplateScopeEnum,
readonly state: App.Enums.DocumentTemplateStateEnum | null,
readonly is_active: boolean,
readonly updated_at: string,
};
export type DocumentTemplateOptionData = {
readonly id: string,
readonly name: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
readonly scope: App.Enums.DocumentTemplateScopeEnum,
};
export type DocumentTemplatePreviewData = {
readonly body: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly show_header: boolean,
readonly show_footer: boolean,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
};
export type DocumentTemplateUpsertData = {
readonly name: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly body: string,
readonly show_header: boolean,
readonly show_footer: boolean,
readonly is_active: boolean,
readonly employment_type: App.Enums.EmploymentContractTypeEnum | null,
};
export type DocumentTokenCatalogData = {
readonly service_agreement: App.Data.DocumentTemplates.DocumentTokenData[],
readonly employment: App.Data.DocumentTemplates.DocumentTokenData[],
readonly nda: App.Data.DocumentTemplates.DocumentTokenData[],
readonly gdpr: App.Data.DocumentTemplates.DocumentTokenData[],
readonly other: App.Data.DocumentTemplates.DocumentTokenData[],
readonly quote: App.Data.DocumentTemplates.DocumentTokenData[],
};
export type DocumentTokenData = {
readonly key: string,
readonly label: string,
readonly kind: App.Enums.DocumentTokenKindEnum,
readonly group: App.Enums.DocumentTokenGroupEnum,
};
}
namespace Documents {
export type DocumentLetterheadData = {
readonly name: string,
readonly address: string | null,
readonly ico: string | null,
};
export type DocumentRenderData = {
readonly html: string,
readonly missing_tokens: string[],
readonly unknown_tokens: string[],
readonly letterhead: App.Data.Documents.DocumentLetterheadData | null,
readonly footer: string[],
};
}
namespace Employees {
export type EmployeeAccessData = {
readonly email: string,
readonly role_name: string,
readonly permissions: string[] | null,
};
export type EmployeeDetailData = {
readonly id: string,
readonly user_id: string | null,
readonly display_name: string,
readonly email: string | null,
readonly phone: string | null,
readonly position: string | null,
readonly role_name: string | null,
readonly employee_type: App.Enums.EmployeeTypeEnum | null,
readonly has_access: boolean,
readonly invitation_pending: boolean,
readonly upcoming_jobs_count: number,
readonly is_active: boolean,
readonly joined_at: string,
readonly user_name: string,
readonly first_name: string | null,
readonly last_name: string | null,
readonly permissions: string[],
readonly employment_contract_id: string | null,
readonly employment_contract: App.Data.Contracts.EmploymentContractData | null,
readonly other_tenants_count: number,
readonly is_owner: boolean,
readonly company_ico: string | null,
readonly company_name: string | null,
readonly contact_email: string | null,
readonly can: Record<string, boolean>,
};
export type EmployeeDocumentConstraintsData = {
readonly allowed_mimes: string[],
readonly max_size_kb: number,
readonly types: App.Enums.DocumentTypeEnum[],
};
export type EmployeeDocumentData = {
readonly id: string,
readonly title: string,
readonly type: App.Enums.DocumentTypeEnum,
readonly file_name: string,
readonly mime_type: string | null,
readonly size: number,
readonly created_at: string,
readonly download_url: string,
};
export type EmployeeDocumentUploadData = {
readonly type: App.Enums.DocumentTypeEnum,
readonly file: File,
readonly title: string | null,
};
export type EmployeeFormContextData = {
readonly roles: App.Data.RoleListItemData[],
readonly permission_groups: App.Data.PermissionGroupData[],
readonly can_manage_access: boolean,
};
export type EmployeeListItemData = {
readonly id: string,
readonly user_id: string | null,
readonly display_name: string,
readonly email: string | null,
readonly phone: string | null,
readonly position: string | null,
readonly role_name: string | null,
readonly employee_type: App.Enums.EmployeeTypeEnum | null,
readonly has_access: boolean,
readonly invitation_pending: boolean,
readonly upcoming_jobs_count: number,
readonly is_active: boolean,
readonly joined_at: string,
};
export type EmployeePersonalData = {
readonly birth_date: string | null,
readonly national_id_masked: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
};
export type EmployeePersonalFormData = {
readonly birth_date: string | null,
readonly national_id: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
};
export type EmployeeRoleData = {
readonly role_name: string,
};
export type EmployeeStoreData = {
readonly has_app_access: boolean,
readonly employee_type: App.Enums.EmployeeTypeEnum,
readonly email: string | null,
readonly first_name: string | null,
readonly last_name: string | null,
readonly phone: string | null,
readonly position: string | null,
readonly role_name: string | null,
readonly company_ico: string | null,
readonly company_name: string | null,
readonly permissions: string[] | null,
readonly employment: App.Data.Contracts.EmploymentContractUpsertData | null,
readonly birth_date: string | null,
readonly national_id: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
};
export type EmployeeUpdateData = {
readonly employee_type: App.Enums.EmployeeTypeEnum | null,
readonly contact_email: string | null,
readonly first_name: string | null,
readonly last_name: string | null,
readonly phone: string | null,
readonly position: string | null,
readonly role_name: string | null,
readonly company_ico: string | null,
readonly company_name: string | null,
readonly permissions: string[] | null,
readonly birth_date: string | null,
readonly national_id: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
};
}
namespace Expenses {
export type ExpenseAnalysisContextData = {
readonly companies: App.Data.Expenses.ExpenseCompanyContextData[],
readonly today: string,
};
export type ExpenseAnalyzeData = {
readonly receipt: File | null,
readonly text: string | null,
};
export type ExpenseCanData = {
readonly update: boolean,
readonly delete: boolean,
readonly confirm: boolean,
};
export type ExpenseCategoryProposalData = {
readonly row: number,
readonly category: App.Enums.ExpenseCategoryEnum | null,
readonly confidence: number,
};
export type ExpenseCompanyContextData = {
readonly id: string,
readonly name: string,
readonly ico: string | null,
readonly iban: string | null,
};
export type ExpenseConfirmData = {
readonly category: App.Enums.ExpenseCategoryEnum,
};
export type ExpenseDetailData = {
readonly id: string,
readonly tenant_id: string,
readonly tenant_name: string | null,
readonly status: App.Enums.ExpenseStatusEnum,
readonly date: string,
readonly amount: string,
readonly currency: App.Enums.CurrencyEnum,
readonly vat_amount: string | null,
readonly supplier_name: string | null,
readonly category: App.Enums.ExpenseCategoryEnum | null,
readonly source: App.Enums.ExpenseSourceEnum,
readonly has_receipt: boolean,
readonly proposed_category: App.Enums.ExpenseCategoryEnum | null,
readonly description: string | null,
readonly bank_reference: string | null,
readonly receipt: App.Data.MediaFileData | null,
readonly ai_proposal: App.Data.Expenses.ExpenseProposalData | null,
readonly confirmed_at: string | null,
readonly can: App.Data.Expenses.ExpenseCanData,
};
export type ExpenseDraftData = {
readonly date: string | null,
readonly amount: string | null,
readonly currency: App.Enums.CurrencyEnum,
readonly vat_amount: string | null,
readonly supplier_name: string | null,
readonly description: string | null,
readonly proposal: App.Data.Expenses.ExpenseProposalData,
};
export type ExpenseFormContextData = {
readonly companies: App.Data.Auth.MobileCompanyData[],
readonly categories: App.Enums.ExpenseCategoryEnum[],
};
export type ExpenseImportAccountCheckData = {
readonly status: App.Enums.ExpenseImportAccountStatusEnum,
readonly statement_iban: string,
readonly owner_tenant_id: string | null,
readonly owner_tenant_name: string | null,
};
export type ExpenseImportData = {
readonly account_iban: string,
readonly rows: App.Data.Expenses.ExpenseImportRowInputData[],
};
export type ExpenseImportPreviewData = {
readonly account_check: App.Data.Expenses.ExpenseImportAccountCheckData,
readonly rows: App.Data.Expenses.ExpenseImportRowData[],
readonly skipped_credit_rows: number,
readonly skipped_other_rows: number,
};
export type ExpenseImportResultData = {
readonly created: number,
readonly already_imported: number,
readonly excluded: number,
};
export type ExpenseImportRowData = {
readonly reference: string,
readonly date: string,
readonly amount: string,
readonly counterparty: string | null,
readonly note: string | null,
readonly proposed_category: App.Enums.ExpenseCategoryEnum | null,
readonly confidence: number,
readonly state: App.Enums.ExpenseImportRowStateEnum,
readonly existing_expense_id: string | null,
readonly include: boolean,
};
export type ExpenseImportRowInputData = {
readonly reference: string,
readonly date: string,
readonly amount: string,
readonly counterparty: string | null,
readonly note: string | null,
readonly proposed_category: App.Enums.ExpenseCategoryEnum | null,
readonly confidence: number,
readonly include: boolean,
};
export type ExpenseImportUploadData = {
readonly file: File,
};
export type ExpenseListItemData = {
readonly id: string,
readonly tenant_id: string,
readonly tenant_name: string | null,
readonly status: App.Enums.ExpenseStatusEnum,
readonly date: string,
readonly amount: string,
readonly currency: App.Enums.CurrencyEnum,
readonly vat_amount: string | null,
readonly supplier_name: string | null,
readonly category: App.Enums.ExpenseCategoryEnum | null,
readonly source: App.Enums.ExpenseSourceEnum,
readonly has_receipt: boolean,
readonly proposed_category: App.Enums.ExpenseCategoryEnum | null,
};
export type ExpenseProposalData = {
readonly category: App.Enums.ExpenseCategoryEnum | null,
readonly tenant_id: string | null,
readonly confidence: number,
readonly reason: string | null,
};
export type ExpenseReceiptUploadData = {
readonly receipt: File,
};
export type ExpenseRowData = {
readonly row: number,
readonly amount: string,
readonly counterparty: string | null,
readonly note: string | null,
};
export type ExpenseStatementImportResultData = {
readonly created: number,
readonly already_imported: number,
readonly excluded: number,
readonly text_withheld: string[],
readonly security_notice: string | null,
};
export type ExpenseStatementPreviewData = {
readonly account_check: App.Data.Expenses.ExpenseImportAccountCheckData,
readonly counts: Record<string, number>,
readonly amount_total: string,
readonly rows: App.Data.Expenses.ExpenseStatementPreviewRowData[],
readonly security_notice: string | null,
};
export type ExpenseStatementPreviewRowData = {
readonly reference: string,
readonly date: string,
readonly amount: string,
readonly state: App.Enums.ExpenseImportRowStateEnum,
readonly existing_expense_id: string | null,
readonly include: boolean,
readonly text_withheld: boolean,
readonly existing_differs: boolean,
};
export type ExpenseStatementRowData = {
readonly reference: string,
readonly date: string,
readonly amount: string,
readonly direction: string,
readonly currency: string,
readonly counterparty: string | null,
readonly note: string | null,
};
export type ExpenseStatementRowsData = {
readonly account_iban: string,
readonly rows: App.Data.Expenses.ExpenseStatementRowData[],
readonly include_references: string[],
readonly exclude_references: string[],
};
export type ExpenseUpsertData = {
readonly date: string,
readonly amount: string,
readonly tenant_id?: string,
readonly currency: App.Enums.CurrencyEnum,
readonly vat_amount: string | null,
readonly supplier_name: string | null,
readonly description: string | null,
readonly category: App.Enums.ExpenseCategoryEnum | null,
readonly status: App.Enums.ExpenseStatusEnum | null,
readonly receipt_uuid: string | null,
readonly source: App.Enums.ExpenseSourceEnum,
readonly ai_proposal: App.Data.Expenses.ExpenseProposalData | null,
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
export type BulkActionResultData = {
readonly action: App.Enums.BulkActionEnum,
readonly processed: number,
readonly skipped: App.Data.Invoices.BulkSkippedItemData[],
};
export type BulkSkippedItemData = {
readonly id: string,
readonly number: string | null,
readonly reason: App.Enums.BulkSkipReasonEnum,
};
export type EInvoiceStatusData = {
readonly status: App.Enums.EfaStatusEnum,
readonly efa_id: string | null,
readonly submitted_at: string | null,
readonly last_error: string | null,
readonly missing_fields: string[],
readonly xml_available: boolean,
readonly can_submit: boolean,
};
export type InvoiceBulkActionData = {
readonly ids: string[],
};
export type InvoiceBulkIdsData = {
readonly ids: string[],
readonly total: number,
readonly truncated: boolean,
};
export type InvoiceBulkLimitsData = {
readonly max_ids: number,
readonly pdf_max_ids: number,
readonly print_all_max: number,
};
export type InvoiceBulkPayData = {
readonly ids: string[],
readonly paid_on: string,
};
export type InvoiceBulkPdfData = {
readonly ids: string[],
};
export type InvoiceBuyerData = {
readonly source: App.Enums.CustomerPartySourceEnum,
readonly name: string | null,
readonly represented_by: string | null,
readonly representative: string | null,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string | null,
readonly email: string | null,
};
export type InvoiceBuyerPreviewData = {
readonly client_id: string,
readonly cleaning_object_id: string | null,
};
export type InvoiceBuyerPreviewResultData = {
readonly buyer: App.Data.Invoices.InvoiceBuyerData,
readonly email_options: string[],
};
export type InvoiceDefaultsData = {
readonly constant_symbol: string | null,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
readonly header_text: string | null,
readonly footer_text: string | null,
};
export type InvoiceDetailData = {
readonly id: string,
readonly tenant_id: string,
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
readonly paid_amount: string,
readonly is_partially_paid: boolean,
readonly is_credit_note: boolean,
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
readonly customer_represented_by: string | null,
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
readonly payments: App.Data.Invoices.InvoicePaymentListItemData[],
readonly efa: App.Data.Invoices.EInvoiceStatusData,
readonly qr_available: boolean,
readonly qr_data_uri: string | null,
readonly supplier_missing_fields: string[],
readonly can_be_issued: boolean,
readonly can_be_sent: boolean,
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
readonly paid_amount: string,
readonly is_partially_paid: boolean,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly issue_date: string,
readonly due_date: string,
readonly is_credit_note: boolean,
readonly sent_at: string | null,
};
export type InvoiceMailPlaceholderCatalogData = {
readonly tokens: App.Data.Contracts.PlaceholderTokenData[],
};
export type InvoicePaymentData = {
readonly paid_on: string,
readonly amount: number,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly note: string | null,
};
export type InvoicePaymentListItemData = {
readonly id: string,
readonly paid_on: string,
readonly amount: string,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly source: App.Enums.PaymentSourceEnum,
readonly note: string | null,
readonly recorded_by_name: string | null,
readonly created_at: string,
};
export type InvoiceSendData = {
readonly recipient_email: string | null,
readonly save_for_future: boolean,
readonly subject: string | null,
readonly body: string | null,
};
export type InvoiceSendDraftData = {
readonly recipient_email: string | null,
readonly email_options: string[],
readonly save_target: App.Enums.InvoiceEmailSaveTargetEnum | null,
readonly subject: string,
readonly body: string,
readonly attachment_name: string,
};
export type InvoiceSendOptionsData = {
readonly email_options: string[],
readonly save_target: App.Enums.InvoiceEmailSaveTargetEnum | null,
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
readonly invoice_number_format: string,
readonly iban: string | null,
readonly vat_rate: number | null,
readonly registration_info: string | null,
readonly recurring_default_state: App.Enums.RecurringDefaultStateEnum,
readonly swift_bic: string | null,
readonly default_constant_symbol: string | null,
readonly default_header_text: string | null,
readonly default_footer_text: string | null,
readonly signature_uuid: string | null,
readonly default_payment_type: App.Enums.PaymentTypeEnum,
readonly default_currency: App.Enums.CurrencyEnum,
readonly default_rounding_mode: App.Enums.RoundingModeEnum,
readonly remove_signature: boolean,
readonly invoice_mail_subject: string | null,
readonly invoice_mail_body: string | null,
readonly peppol_participant_id: string | null,
readonly representative_name: string | null,
readonly default_quote_included_text: string | null,
readonly signing_place: string | null,
};
export type InvoiceSignatureConstraintsData = {
readonly allowed_mimes: string[],
readonly max_size_kb: number,
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
export type PohodaExportData = {
readonly from: string,
readonly to: string,
};
export type PohodaExportPreviewData = {
readonly count: number,
readonly skipped_non_eur: number,
};
export type VatBreakdownLineData = {
readonly rate: number,
readonly base: number,
readonly vat: number,
readonly total: number,
};
}
namespace Notifications {
export type NotificationBellData = {
readonly unread_count: number,
readonly recent: App.Data.Notifications.NotificationListItemData[],
};
export type NotificationListItemData = {
readonly id: string,
readonly type: App.Enums.NotificationTypeEnum,
readonly title: string,
readonly body: string,
readonly url: string | null,
readonly route: string,
readonly read_at: string | null,
readonly created_at: string,
};
export type NotificationPreferenceItemData = {
readonly type: App.Enums.NotificationTypeEnum,
readonly label: string,
readonly mail: boolean,
readonly push: boolean,
readonly configurable: boolean,
};
export type NotificationPreferenceUpdateItemData = {
readonly type: App.Enums.NotificationTypeEnum,
readonly mail: boolean,
readonly push: boolean,
};
export type NotificationPreferencesData = {
readonly items: App.Data.Notifications.NotificationPreferenceItemData[],
};
export type NotificationPreferencesUpdateData = {
readonly preferences: App.Data.Notifications.NotificationPreferenceUpdateItemData[],
};
}
namespace Objects {
export type ObjectBillingData = {
readonly name: string | null,
readonly ico: string | null,
readonly dic: string | null,
readonly vat_number: string | null,
readonly peppol_participant_id: string | null,
readonly street: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly email: string | null,
};
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
readonly contacts: App.Data.Contacts.ContactData[] | null,
readonly billing: App.Data.Objects.ObjectBillingData | null,
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
readonly contacts_count: number | null,
readonly primary_contact_email: string | null,
readonly primary_contact_phone: string | null,
};
export type ObjectOptionData = {
readonly id: string,
readonly name: string,
readonly client_id: string,
readonly client_name: string | null,
readonly is_active: boolean,
readonly billing_name: string | null,
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
readonly contacts: App.Data.Contacts.ContactData[],
readonly billing_name: string | null,
readonly billing_ico: string | null,
readonly billing_dic: string | null,
readonly billing_vat_number: string | null,
readonly billing_peppol_participant_id: string | null,
readonly billing_street: string | null,
readonly billing_city: string | null,
readonly billing_postal_code: string | null,
readonly billing_email: string | null,
};
}
namespace Profile {
export type McpTokenCreateData = {
readonly name: string,
};
export type McpTokenCreatedData = {
readonly token: App.Data.Profile.McpTokenData,
readonly plain_text_token: string,
};
export type McpTokenData = {
readonly id: string,
readonly name: string,
readonly created_at: string,
readonly last_used_at: string | null,
readonly expires_at: string | null,
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
readonly document_template_id: string | null,
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
readonly period_totals: App.Data.Quotes.QuotePeriodTotalsData[],
readonly included_text: string | null,
readonly document: App.Data.MediaFileData | null,
readonly text_document: App.Data.Quotes.QuoteTextDocumentData | null,
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
readonly templates: App.Data.Quotes.QuoteTemplateOptionData[],
readonly tokens: App.Data.DocumentTemplates.DocumentTokenData[],
readonly letterhead: App.Data.Documents.DocumentLetterheadData,
readonly footer: string[],
readonly default_included_text: string | null,
};
export type QuoteInvoiceLinkData = {
readonly id: string,
readonly number: string | null,
readonly status: App.Enums.InvoiceStatusEnum,
};
export type QuoteItemData = {
readonly id: string | null,
readonly description: string,
readonly frequency: App.Enums.TaskFrequencyEnum | null,
readonly quantity: number,
readonly unit: string | null,
readonly unit_price: number,
readonly discount_percent: number,
readonly vat_rate: number,
readonly billing_period: App.Enums.QuoteBillingPeriodEnum,
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
readonly period_totals: App.Data.Quotes.QuotePeriodTotalsData[],
readonly issue_date: string,
readonly valid_until: string,
readonly has_document: boolean,
};
export type QuotePeriodTotalsData = {
readonly billing_period: App.Enums.QuoteBillingPeriodEnum,
readonly subtotal: string,
readonly vat_amount: string,
readonly total: string,
};
export type QuoteTemplateOptionData = {
readonly id: string,
readonly name: string,
readonly scope: App.Enums.DocumentTemplateScopeEnum,
readonly body: string,
};
export type QuoteTextDocumentData = {
readonly id: string,
readonly document_template_id: string | null,
readonly document_template_name: string | null,
readonly body: string,
readonly show_header: boolean,
readonly show_footer: boolean,
readonly is_frozen: boolean,
readonly missing_tokens: string[],
readonly tokens: App.Data.DocumentTemplates.DocumentTokenData[],
readonly letterhead: App.Data.Documents.DocumentLetterheadData,
readonly footer: string[],
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
readonly document_template_id: string | null,
readonly document_body: string | null,
readonly included_text: string | null,
readonly document_show_header: boolean,
readonly document_show_footer: boolean,
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
readonly billed_month: App.Enums.RecurringBilledMonthEnum,
readonly client_id: string | null,
readonly customer_name: string | null,
readonly buyer: App.Data.Invoices.InvoiceBuyerData,
readonly cleaning_object_name: string | null,
readonly day_of_month: number,
readonly next_run_at: string | null,
readonly occurrences_generated: number,
readonly occurrences_limit: number | null,
readonly auto_issue: boolean,
readonly auto_send: boolean,
readonly start_date: string,
readonly end_date: string | null,
readonly type: App.Enums.InvoiceTypeEnum,
readonly cleaning_object_id: string | null,
readonly last_generated_at: string | null,
readonly due_days: number,
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
export type RecurringInvoicePrefillData = {
readonly quote_id: string,
readonly client_id: string | null,
readonly cleaning_object_id: string | null,
readonly name: string,
readonly frequency: App.Enums.RecurringFrequencyEnum,
readonly customer_name: string | null,
readonly customer_email: string | null,
readonly customer_street: string | null,
readonly customer_city: string | null,
readonly customer_postal_code: string | null,
readonly currency: App.Enums.CurrencyEnum,
readonly note: string | null,
readonly items: App.Data.RecurringInvoices.RecurringInvoiceItemData[],
};
export type RecurringInvoiceUpsertData = {
readonly client_id: string | null,
readonly cleaning_object_id: string | null,
readonly name: string,
readonly type: App.Enums.InvoiceTypeEnum,
readonly frequency: App.Enums.RecurringFrequencyEnum,
readonly day_of_month: number,
readonly auto_issue: boolean,
readonly start_date: string,
readonly end_date: string | null,
readonly occurrences_limit: number | null,
readonly due_days: number,
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
readonly auto_send: boolean,
readonly payment_type: App.Enums.PaymentTypeEnum,
readonly currency: App.Enums.CurrencyEnum,
readonly rounding_mode: App.Enums.RoundingModeEnum,
readonly billed_month: App.Enums.RecurringBilledMonthEnum,
};
}
namespace Schedule {
export type JobAssignData = {
readonly assigned_membership_id: string | null,
};
export type JobCalendarFilterData = {
readonly from: string,
readonly to: string,
readonly cleaning_object_id: string | null,
readonly assigned_membership_id: string | null,
readonly status: App.Enums.JobStatusEnum | null,
readonly type: App.Enums.JobTypeEnum | null,
};
export type JobCalendarItemData = {
readonly id: string,
readonly scheduled_date: string,
readonly start_time: string | null,
readonly end_time: string | null,
readonly object_name: string,
readonly assignee_display_name: string | null,
readonly type: App.Enums.JobTypeEnum,
readonly status: App.Enums.JobStatusEnum,
};
export type JobDetailData = {
readonly id: string,
readonly scheduled_date: string,
readonly start_time: string | null,
readonly end_time: string | null,
readonly cleaning_object_id: string,
readonly object_name: string,
readonly client_name: string,
readonly assigned_membership_id: string | null,
readonly assignee_display_name: string | null,
readonly type: App.Enums.JobTypeEnum,
readonly status: App.Enums.JobStatusEnum,
readonly is_invoiced: boolean,
readonly client_id: string | null,
readonly work_breakdown_id: string | null,
readonly work_breakdown_task_id: string | null,
readonly task_name: string | null,
readonly contract_id: string | null,
readonly contract_title: string | null,
readonly invoice_id: string | null,
readonly note: string | null,
readonly started_at: string | null,
readonly completed_at: string | null,
readonly cancelled_at: string | null,
readonly is_editable: boolean,
readonly can_be_assigned: boolean,
readonly can_be_cancelled: boolean,
readonly can_be_started: boolean,
readonly can_be_completed: boolean,
readonly can_be_unapproved: boolean,
readonly can: Record<string, boolean>,
};
export type JobFormContextData = {
readonly objects: App.Data.Objects.ObjectOptionData[],
readonly memberships: App.Data.Contracts.MembershipOptionData[],
};
export type JobListItemData = {
readonly id: string,
readonly scheduled_date: string,
readonly start_time: string | null,
readonly end_time: string | null,
readonly cleaning_object_id: string,
readonly object_name: string,
readonly client_name: string,
readonly assigned_membership_id: string | null,
readonly assignee_display_name: string | null,
readonly type: App.Enums.JobTypeEnum,
readonly status: App.Enums.JobStatusEnum,
readonly is_invoiced: boolean,
};
export type JobStoreData = {
readonly cleaning_object_id: string,
readonly type: App.Enums.JobTypeEnum,
readonly scheduled_date: string,
readonly start_time: string | null,
readonly end_time: string | null,
readonly assigned_membership_id: string | null,
readonly note: string | null,
};
export type JobUpdateData = {
readonly cleaning_object_id: string,
readonly type: App.Enums.JobTypeEnum,
readonly scheduled_date: string,
readonly start_time: string | null,
readonly end_time: string | null,
readonly note: string | null,
};
export type WorkBreakdownDetailData = {
readonly id: string,
readonly cleaning_object_id: string,
readonly name: string,
readonly is_active: boolean,
readonly contract_id: string | null,
readonly contract_title: string | null,
readonly contract_status: App.Enums.ContractStatusEnum | null,
readonly source_quote_id: string | null,
readonly tasks: App.Data.Schedule.WorkBreakdownTaskData[],
};
export type WorkBreakdownTaskData = {
readonly id: string,
readonly name: string,
readonly description: string | null,
readonly frequency: App.Enums.TaskFrequencyEnum,
readonly position: number,
};
}
namespace Tasks {
export type TaskAssignData = {
readonly assigned_membership_id: string | null,
};
export type TaskAttachmentConstraintsData = {
readonly max_size_kb: number,
readonly max_count: number,
readonly allowed_mimes: string[],
readonly allowed_extensions: string[],
};
export type TaskAttachmentData = {
readonly id: string,
readonly name: string,
readonly mime_type: string,
readonly size: number,
readonly previewable: boolean,
readonly uploaded_by_name: string | null,
readonly created_at: string,
readonly download_url: string,
readonly can_delete: boolean,
};
export type TaskAttachmentListData = {
readonly attachments: App.Data.Tasks.TaskAttachmentData[],
readonly can_add: boolean,
readonly constraints: App.Data.Tasks.TaskAttachmentConstraintsData,
};
export type TaskAttachmentUploadData = {
readonly file: File,
};
export type TaskBoardColumnData = {
readonly status: App.Enums.TaskStatusEnum,
readonly total: number,
readonly tasks: App.Data.Tasks.TaskListItemData[],
readonly has_more: boolean,
};
export type TaskBoardData = {
readonly columns: App.Data.Tasks.TaskBoardColumnData[],
};
export type TaskCanData = {
readonly update: boolean,
readonly delete: boolean,
readonly assign: boolean,
};
export type TaskCommentData = {
readonly id: string,
readonly author_kind: App.Enums.TaskCommentAuthorEnum,
readonly author_name: string,
readonly body: string,
readonly created_at: string,
readonly is_mine: boolean,
readonly can_delete: boolean,
readonly answers_comment_id: string | null,
};
export type TaskCommentStoreData = {
readonly body: string,
readonly answers_comment_id: string | null,
};
export type TaskDetailData = {
readonly id: string,
readonly tenant_id: string | null,
readonly tenant_name: string | null,
readonly title: string,
readonly status: App.Enums.TaskStatusEnum,
readonly priority: App.Enums.TaskPriorityEnum,
readonly due_at: string | null,
readonly assignee_name: string | null,
readonly subject: App.Data.Tasks.TaskSubjectData | null,
readonly is_global: boolean,
readonly created_at: string,
readonly description: string | null,
readonly remind_at: string | null,
readonly remind_on: string | null,
readonly remind_slot: App.Enums.TaskReminderSlotEnum | null,
readonly completed_at: string | null,
readonly source: App.Enums.TaskSourceEnum,
readonly assigned_membership_id: string | null,
readonly created_by_name: string,
readonly can: App.Data.Tasks.TaskCanData,
readonly attachments_count: number,
readonly is_active: boolean,
};
export type TaskDraftData = {
readonly title: string,
readonly description: string | null,
readonly due_at: string | null,
readonly priority: App.Enums.TaskPriorityEnum,
readonly tenant_id: string | null,
readonly assigned_membership_id: string | null,
readonly assignee_hint: string | null,
};
export type TaskFormContextData = {
readonly companies: App.Data.Auth.MobileCompanyData[],
readonly members: Record<string, App.Data.Tasks.TaskMemberOptionData[]>,
readonly reminder_slots: App.Data.Tasks.TaskReminderSlotData[],
readonly default_remind_on: string,
readonly default_remind_slot: App.Enums.TaskReminderSlotEnum,
};
export type TaskListItemData = {
readonly id: string,
readonly tenant_id: string | null,
readonly tenant_name: string | null,
readonly title: string,
readonly status: App.Enums.TaskStatusEnum,
readonly priority: App.Enums.TaskPriorityEnum,
readonly due_at: string | null,
readonly assignee_name: string | null,
readonly subject: App.Data.Tasks.TaskSubjectData | null,
readonly is_global: boolean,
readonly created_at: string,
readonly comments_count: number,
readonly attachments_count: number,
readonly can_update: boolean,
readonly is_active: boolean,
readonly ai_run_status: App.Enums.TaskRunStatusEnum | null,
};
export type TaskMemberOptionData = {
readonly id: string,
readonly name: string,
};
export type TaskParseData = {
readonly text: string,
readonly company_id: string | null,
};
export type TaskQuestionData = {
readonly comment_id: string,
readonly answered: boolean,
readonly can_answer: boolean,
};
export type TaskReminderSlotData = {
readonly slot: App.Enums.TaskReminderSlotEnum,
readonly hour: number,
};
export type TaskRunCompanyData = {
readonly id: string,
readonly name: string,
};
export type TaskRunData = {
readonly id: string,
readonly status: App.Enums.TaskRunStatusEnum,
readonly is_active: boolean,
readonly closed_task: boolean,
readonly started_by_name: string,
readonly tenant_id: string,
readonly tenant_name: string,
readonly finished_at: string | null,
readonly actions: App.Data.Ai.AssistantActionData[],
};
export type TaskRunStartData = {
readonly company_id: string | null,
};
export type TaskRunStatusData = {
readonly latest_run_id: string | null,
readonly status: App.Enums.TaskRunStatusEnum | null,
readonly is_active: boolean,
};
export type TaskStatusChangeData = {
readonly status: App.Enums.TaskStatusEnum,
};
export type TaskSubjectData = {
readonly type: string,
readonly id: string,
readonly label: string,
readonly url: string | null,
};
export type TaskThreadData = {
readonly comments: App.Data.Tasks.TaskCommentData[],
readonly latest_run: App.Data.Tasks.TaskRunData | null,
readonly question: App.Data.Tasks.TaskQuestionData | null,
readonly pending_actions: App.Data.Ai.PendingActionData[],
readonly ai_available: boolean,
readonly can_comment: boolean,
readonly can_start_run: boolean,
readonly run_companies: App.Data.Tasks.TaskRunCompanyData[],
readonly max_body_chars: number,
};
export type TaskUpsertData = {
readonly title: string,
readonly priority: App.Enums.TaskPriorityEnum,
readonly tenant_id?: string | null,
readonly description: string | null,
readonly due_at: string | null,
readonly remind_at: string | null,
readonly remind_on: string | null,
readonly remind_slot: App.Enums.TaskReminderSlotEnum | null,
readonly assigned_membership_id: string | null,
readonly taskable_type: string | null,
readonly taskable_id: string | null,
readonly source: App.Enums.TaskSourceEnum,
readonly status: App.Enums.TaskStatusEnum | null,
};
}
namespace Tenants {
export type AddTenantData = {
readonly name: string,
readonly ico: string,
readonly dic: string | null,
readonly vat_number: string | null,
readonly is_vat_payer: boolean,
readonly address_line: string | null,
readonly city: string | null,
readonly postal_code: string | null,
readonly country: string,
readonly contact_email: string | null,
readonly contact_phone: string | null,
readonly iban: string | null,
readonly swift_bic: string | null,
readonly color: App.Enums.TenantColorEnum | null,
readonly copy_settings: boolean,
};
export type TenantListItemData = {
readonly id: string,
readonly name: string,
readonly is_active: boolean,
readonly color: App.Enums.TenantColorEnum | null,
};
export type TenantSignatureData = {
readonly url: string,
readonly file_name: string,
readonly mime_type: string,
readonly size: number,
readonly uploaded_at: string,
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
export type TenantSwitchData = {
readonly redirect_to: string | null,
};
}
}
namespace Enums {
export type ActionChannelEnum = "mcp" | "assistant" | "task_run";
export type ActivityRevertBlockReasonEnum = "unsupported_operation" | "unsupported_model" | "lifecycle_field" | "no_company" | "forbidden" | "subject_missing" | "subject_deleted" | "subject_not_deleted" | "parent_deleted" | "already_reverted" | "conflict" | "has_later_changes" | "not_draft" | "primary_contact" | "no_changes" | "unique_conflict" | "batch_member_blocked";
export type ActivityRevertStatusEnum = "available" | "unavailable" | "reverted";
export type AssistantActionTypeEnum = "open" | "created" | "updated";
export type AssistantRoleEnum = "user" | "assistant";
export type BulkActionEnum = "pay" | "send" | "issue";
export type BulkSkipReasonEnum = "not_found" | "not_issued" | "no_email" | "not_draft" | "not_payable" | "supplier_incomplete" | "number_taken";
export type ClientTypeEnum = "corporate" | "private";
export type ConfirmationChannelEnum = "chat" | "app";
export type ContractCategoryEnum = "service_agreement" | "employment" | "nda" | "gdpr" | "other";
export type ContractStatusEnum = "draft" | "active" | "expired" | "terminated";
export type ContractTermTypeEnum = "fixed" | "indefinite";
export type ContractableTypeEnum = "cleaning_object" | "tenant_membership" | "client";
export type CurrencyEnum = "EUR" | "CZK" | "USD";
export type CustomerPartySourceEnum = "object_billing" | "client" | "entered";
export type DashboardAlertTypeEnum = "overdue_invoice" | "supplier_incomplete" | "unassigned_job" | "contract_expiring" | "quote_expiring";
export type DevicePlatformEnum = "android" | "ios";
export type DocumentTemplateScopeEnum = "global" | "override" | "firm";
export type DocumentTemplateStateEnum = "base" | "customized" | "base_changed";
export type DocumentTokenGroupEnum = "tenant" | "contract" | "client" | "object" | "employee" | "employment" | "customer" | "quote" | "conditions" | "blocks";
export type DocumentTokenKindEnum = "text" | "optional" | "block" | "flag";
export type DocumentTypeEnum = "service_agreement" | "employment" | "nda" | "gdpr" | "other" | "quote" | "employee_id_card" | "employee_passport" | "employee_residence_permit" | "employee_work_permit" | "employee_medical" | "employee_training" | "employee_other";
export type EfaStatusEnum = "none" | "ready" | "generated" | "submitted" | "accepted" | "rejected" | "failed";
export type EmployeeTypeEnum = "brigade" | "tpp" | "self_employed";
export type EmploymentContractTypeEnum = "dpp" | "dpc" | "tpp" | "self_employed";
export type ExpenseCategoryEnum = "fuel" | "materials" | "cleaning_supplies" | "equipment" | "services" | "rent" | "utilities" | "wages" | "insurance" | "fees" | "bank_charges" | "other";
export type ExpenseImportAccountStatusEnum = "matched" | "other_company" | "unknown_account" | "company_iban_missing";
export type ExpenseImportRowStateEnum = "new" | "already_imported" | "previously_deleted" | "possible_match";
export type ExpenseSourceEnum = "manual" | "receipt" | "bank_statement" | "mcp";
export type ExpenseStatusEnum = "draft" | "confirmed";
export type InvitationAcceptStateEnum = "expired" | "wrong_user" | "existing_user" | "new_user";
export type InvitationStatusEnum = "pending" | "accepted" | "revoked" | "expired";
export type InvoiceEmailSaveTargetEnum = "recurring_invoice" | "object";
export type InvoiceStatusEnum = "draft" | "issued" | "paid" | "overdue" | "cancelled";
export type InvoiceTypeEnum = "monthly" | "one_off" | "special";
export type JobStatusEnum = "unassigned" | "planned" | "in_progress" | "completed" | "unapproved" | "cancelled";
export type JobTypeEnum = "regular" | "one_off" | "special";
export type MobileSessionRevokeReasonEnum = "logout" | "password_changed" | "password_reset" | "access_lost" | "reuse_detected" | "expired" | "membership_lost";
export type NavIconEnum = "dashboard" | "buildings" | "team" | "clients" | "quotes" | "invoices" | "recurring_invoices" | "contracts" | "documents" | "schedule" | "tasks" | "expenses" | "notifications" | "notification_settings" | "pending_actions" | "users" | "roles" | "audit_logs" | "media" | "profile" | "invoice_settings" | "settings";
export type NotificationTypeEnum = "invitation.created" | "invoice.issued" | "invoice.overdue" | "invoice.issue_reminder" | "contract.expiring" | "contract.expired" | "quote.sent" | "quote.expiring" | "quote.expired" | "task.due" | "task.assigned" | "task.commented" | "task.ai_run";
export type ObjectTypeEnum = "office" | "apartment" | "house" | "residential_building" | "common_areas";
export type PaymentSourceEnum = "manual" | "bank_import" | "legacy_import";
export type PaymentTypeEnum = "transfer" | "cash" | "card" | "cod" | "other";
export type PendingActionEnum = "issue_invoice" | "send_invoice" | "send_quote" | "sign_contract" | "cancel_invoice" | "delete_invoice_draft" | "delete_quote" | "delete_task" | "delete_expense";
export type PendingActionStatusEnum = "pending" | "executed" | "cancelled" | "expired";
export type PermissionEnum = "view employees" | "create employees" | "edit employees" | "delete employees" | "assign employees" | "view employee_personal_data" | "view employee_documents" | "manage employee_documents" | "manage employee_access" | "view roles" | "create roles" | "edit roles" | "delete roles" | "view audit logs" | "revert audit logs" | "view api docs" | "view media" | "upload files" | "view clients" | "create clients" | "edit clients" | "delete clients" | "view objects" | "create objects" | "edit objects" | "delete objects" | "view all objects" | "view quotes" | "create quotes" | "edit quotes" | "send quotes" | "approve quotes" | "delete quotes" | "view contracts" | "create contracts" | "edit contracts" | "terminate contracts" | "delete contracts" | "view document_templates" | "create document_templates" | "edit document_templates" | "delete document_templates" | "view schedule" | "create schedule" | "edit schedule" | "assign cleaners" | "view all schedule" | "view invoices" | "create invoices" | "edit invoices" | "cancel invoices" | "manage billing settings" | "view recurring_invoices" | "create recurring_invoices" | "edit recurring_invoices" | "delete recurring_invoices" | "view notifications" | "configure notifications" | "access mobile app" | "view tasks" | "create tasks" | "edit tasks" | "delete tasks" | "view all tasks" | "run tasks with ai" | "use assistant" | "view expenses" | "manage expenses";
export type QuoteBillingPeriodEnum = "one_off" | "monthly" | "quarterly" | "yearly";
export type QuoteKindEnum = "itemized" | "document";
export type QuoteStatusEnum = "draft" | "sent" | "accepted" | "rejected" | "expired";
export type RecurringBilledMonthEnum = "none" | "current" | "previous";
export type RecurringDefaultStateEnum = "draft" | "issued";
export type RecurringFrequencyEnum = "monthly" | "every_2_months" | "quarterly" | "semi_annually" | "annually";
export type RecurringInvoiceStatusEnum = "active" | "paused" | "completed" | "cancelled";
export type RoundingModeEnum = "none" | "document" | "cash_005";
export type SupportedLanguage = "sk" | "en" | "uk";
export type TaskCommentAuthorEnum = "user" | "ai";
export type TaskFrequencyEnum = "one_time" | "weekly_1x" | "weekly_2x" | "weekly_3x" | "biweekly" | "monthly" | "bimonthly" | "seasonal";
export type TaskIndexViewEnum = "table" | "board";
export type TaskPriorityEnum = "low" | "normal" | "high";
export type TaskReminderSlotEnum = "morning" | "noon" | "evening";
export type TaskRunOutcomeEnum = "done" | "question" | "cannot";
export type TaskRunStatusEnum = "queued" | "running" | "done" | "needs_input" | "awaiting_confirmation" | "could_not_finish" | "blocked" | "failed";
export type TaskSourceEnum = "manual" | "voice" | "assistant" | "mcp";
export type TaskStatusEnum = "open" | "in_progress" | "waiting" | "nice_to_have" | "done";
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
