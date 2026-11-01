import type { ContactRow } from '@/Components/Contacts/contact';

export interface ObjectFormData {
    client_id: string;
    type: App.Enums.ObjectTypeEnum;
    name: string;
    street: string;
    city: string;
    postal_code: string;
    country: string;
    access_code: string;
    key_box_code: string;
    key_count: number | null;
    special_instructions: string;
    area_sqm: number | null;
    floor: number | null;
    is_active: boolean;
    contacts: ContactRow[];
    billing_name: string;
    billing_ico: string;
    billing_dic: string;
    billing_vat_number: string;
    billing_peppol_participant_id: string;
    billing_street: string;
    billing_city: string;
    billing_postal_code: string;
}

export type ObjectBillingFormData = Pick<
    ObjectFormData,
    | 'billing_name'
    | 'billing_ico'
    | 'billing_dic'
    | 'billing_vat_number'
    | 'billing_peppol_participant_id'
    | 'billing_street'
    | 'billing_city'
    | 'billing_postal_code'
>;

export function emptyBillingFormData(): ObjectBillingFormData {
    return {
        billing_name: '',
        billing_ico: '',
        billing_dic: '',
        billing_vat_number: '',
        billing_peppol_participant_id: '',
        billing_street: '',
        billing_city: '',
        billing_postal_code: '',
    };
}

export function billingToFormData(billing: App.Data.Objects.ObjectBillingData | null): ObjectBillingFormData {
    if (billing === null) return emptyBillingFormData();

    return {
        billing_name: billing.name,
        billing_ico: billing.ico ?? '',
        billing_dic: billing.dic ?? '',
        billing_vat_number: billing.vat_number ?? '',
        billing_peppol_participant_id: billing.peppol_participant_id ?? '',
        billing_street: billing.street ?? '',
        billing_city: billing.city ?? '',
        billing_postal_code: billing.postal_code ?? '',
    };
}

export function objectToUpsertPayload(object: App.Data.Objects.ObjectDetailData): ObjectFormData {
    return {
        client_id: object.client_id,
        type: object.type,
        name: object.name,
        street: object.street ?? '',
        city: object.city ?? '',
        postal_code: object.postal_code ?? '',
        country: object.country,
        access_code: object.access_code ?? '',
        key_box_code: object.key_box_code ?? '',
        key_count: object.key_count,
        special_instructions: object.special_instructions ?? '',
        area_sqm: object.area_sqm === null ? null : Number(object.area_sqm),
        floor: object.floor,
        is_active: object.is_active,
        contacts: (object.contacts ?? []).map((c) => ({ ...c })),
        ...billingToFormData(object.billing),
    };
}
