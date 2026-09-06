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
    };
}
