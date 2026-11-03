export type ImageIntent = { kind: 'keep' } | { kind: 'upload'; uuid: string } | { kind: 'remove' };

export interface ImageFields {
    uuid: string | null;
    remove: boolean;
}

export function imageIntentToFields(intent: ImageIntent): ImageFields {
    switch (intent.kind) {
        case 'upload':
            return { uuid: intent.uuid, remove: false };
        case 'remove':
            return { uuid: null, remove: true };
        case 'keep':
        default:
            return { uuid: null, remove: false };
    }
}

export function fieldsToImageIntent(uuid: string | null, remove: boolean): ImageIntent {
    if (uuid !== null) {
        return { kind: 'upload', uuid };
    }
    if (remove) {
        return { kind: 'remove' };
    }
    return { kind: 'keep' };
}
