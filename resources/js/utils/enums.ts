// Runtime enum value lists + label-key helpers.
// generated.d.ts ships only the TS union types (no runtime value) — these arrays mirror
// the PHP backed enums so pages can build select/radio options. Keep in sync manually:
// the TS union catches a wrong member here, but not a member missing from this list.

export const CLIENT_TYPES: readonly App.Enums.ClientTypeEnum[] = ['corporate', 'private'];

export const OBJECT_TYPES: readonly App.Enums.ObjectTypeEnum[] = ['office', 'apartment', 'house', 'common_areas'];

export function clientTypeKey(type: App.Enums.ClientTypeEnum): string {
    return `client_type_${type}`;
}

export function objectTypeKey(type: App.Enums.ObjectTypeEnum): string {
    return `object_type_${type}`;
}
