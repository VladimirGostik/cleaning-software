import type { EmploymentFormData } from '@/Components/Contracts/EmploymentContractFields.vue';

export interface EmployeeFormData {
    has_app_access: boolean;
    employee_type: App.Enums.EmployeeTypeEnum | null;
    email: string;
    contact_email: string | null;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    position: string | null;
    role_name: string;
    permissions: string[];
    employment: EmploymentFormData | null;
    company_ico: string | null;
    company_name: string | null;
    birth_date: string | null;
    national_id: string | null;
    street: string | null;
    city: string | null;
    postal_code: string | null;
}

function personalPayload(data: EmployeeFormData, hasPersonal: boolean): Record<string, unknown> {
    if (!hasPersonal) return {};

    return {
        birth_date: data.birth_date || null,
        national_id: data.national_id || null,
        street: data.street || null,
        city: data.city || null,
        postal_code: data.postal_code || null,
    };
}

function commonPayload(data: EmployeeFormData): Record<string, unknown> {
    return {
        employee_type: data.employee_type,
        first_name: data.first_name || null,
        last_name: data.last_name || null,
        phone: data.phone || null,
        position: data.position || null,
        role_name: data.role_name || null,
        permissions: data.permissions,
        company_ico: data.company_ico || null,
        company_name: data.company_name || null,
    };
}

export function employeeStorePayload(data: EmployeeFormData, hasPersonal: boolean): Record<string, unknown> {
    return {
        ...commonPayload(data),
        ...(data.has_app_access ? {} : { role_name: null, permissions: [] }),
        has_app_access: data.has_app_access,
        email: data.email || null,
        employment: data.employment,
        ...personalPayload(data, hasPersonal),
    };
}

export function employeeUpdatePayload(data: EmployeeFormData, hasPersonal: boolean): Record<string, unknown> {
    return {
        ...commonPayload(data),
        contact_email: data.contact_email || null,
        ...personalPayload(data, hasPersonal),
    };
}
