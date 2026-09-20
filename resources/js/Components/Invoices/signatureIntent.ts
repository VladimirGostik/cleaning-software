export type SignatureIntent = { kind: 'keep' } | { kind: 'upload'; uuid: string } | { kind: 'remove' };

export interface SignatureFields {
    signature_uuid: string | null;
    remove_signature: boolean;
}

export function signatureIntentToFields(intent: SignatureIntent): SignatureFields {
    switch (intent.kind) {
        case 'upload':
            return { signature_uuid: intent.uuid, remove_signature: false };
        case 'remove':
            return { signature_uuid: null, remove_signature: true };
        case 'keep':
        default:
            return { signature_uuid: null, remove_signature: false };
    }
}

export function fieldsToSignatureIntent(fields: SignatureFields): SignatureIntent {
    if (fields.signature_uuid !== null) {
        return { kind: 'upload', uuid: fields.signature_uuid };
    }
    if (fields.remove_signature) {
        return { kind: 'remove' };
    }
    return { kind: 'keep' };
}
