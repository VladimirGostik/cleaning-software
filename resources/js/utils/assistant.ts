import { HttpError } from '@/utils/http';

export const MAX_MESSAGES = 20;
export const MAX_MESSAGE_CHARS = 4000;
export const MAX_TOTAL_CHARS = 12000;
export const REQUEST_TIMEOUT_MS = 90_000;
export const FILES_REQUEST_TIMEOUT_MS = 120_000;

export const ASSISTANT_ATTACHMENT_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'] as const;
export const ASSISTANT_ATTACHMENT_MAX_FILES = 5;
export const ASSISTANT_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
export const ASSISTANT_PDF_MAX_BYTES = 10 * 1024 * 1024;
export const ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES = 12 * 1024 * 1024;

const EXTENSION_KINDS: Record<string, AssistantAttachmentKind> = {
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    webp: 'image',
    pdf: 'pdf',
};

export type AssistantAttachmentKind = 'image' | 'pdf';
export type AssistantAttachmentRejection = 'invalid_type' | 'too_large' | 'too_many' | 'total_too_large';

export type AssistantErrorKind =
    | 'ai'
    | 'validation'
    | 'rate'
    | 'forbidden'
    | 'session'
    | 'outcome_unknown'
    | 'too_large'
    | 'other';

export interface AssistantError {
    kind: AssistantErrorKind;
    message: string | null;
}

interface Turn {
    role: App.Enums.AssistantRoleEnum;
    content: string;
    attachmentNames?: readonly string[];
}

export interface PayloadMessage {
    role: App.Enums.AssistantRoleEnum;
    content: string;
    attachment_names?: string[];
}

const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
const LINK_ALLOWLIST = new RegExp(`^/((tasks|invoices|quotes|expenses)/${UUID}|pending-actions)$`, 'i');

function total(turns: Turn[]): number {
    return turns.reduce((sum, turn) => sum + turn.content.length, 0);
}

export function buildPayloadMessages(history: readonly Turn[]): PayloadMessage[] {
    let turns = history
        .filter((turn) => turn.content.trim() !== '' || (turn.attachmentNames?.length ?? 0) > 0)
        .map((turn): PayloadMessage => {
            const message: PayloadMessage = { role: turn.role, content: turn.content.slice(0, MAX_MESSAGE_CHARS) };
            if (turn.attachmentNames !== undefined && turn.attachmentNames.length > 0) {
                message.attachment_names = [...turn.attachmentNames];
            }

            return message;
        })
        .slice(-MAX_MESSAGES);

    while (turns.length > 1 && total(turns) > MAX_TOTAL_CHARS) {
        turns = turns.slice(1);
    }

    return turns;
}

export function buildAssistantFormData(companyId: string, messages: readonly PayloadMessage[], files: readonly File[]) {
    const form = new FormData();
    form.append('company_id', companyId);

    messages.forEach((message, i) => {
        form.append(`messages[${i}][role]`, message.role);
        form.append(`messages[${i}][content]`, message.content);
        message.attachment_names?.forEach((name) => form.append(`messages[${i}][attachment_names][]`, name));
    });
    files.forEach((file) => form.append('attachments[]', file));

    return form;
}

export function assistantAttachmentKind(file: { name: string; type: string }): AssistantAttachmentKind | null {
    if (file.type !== '') {
        if (!(ASSISTANT_ATTACHMENT_MIMES as readonly string[]).includes(file.type)) return null;

        return file.type === 'application/pdf' ? 'pdf' : 'image';
    }

    const dot = file.name.lastIndexOf('.');

    return dot > -1 ? (EXTENSION_KINDS[file.name.slice(dot + 1).toLowerCase()] ?? null) : null;
}

export function assistantAttachmentRejection(
    file: File,
    selected: readonly File[],
): AssistantAttachmentRejection | null {
    const kind = assistantAttachmentKind(file);
    if (kind === null) return 'invalid_type';
    if (file.size > (kind === 'pdf' ? ASSISTANT_PDF_MAX_BYTES : ASSISTANT_IMAGE_MAX_BYTES)) return 'too_large';
    if (selected.length >= ASSISTANT_ATTACHMENT_MAX_FILES) return 'too_many';

    const sum = selected.reduce((bytes, f) => bytes + f.size, file.size);

    return sum > ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES ? 'total_too_large' : null;
}

export function isAttachmentRejection(e: unknown): boolean {
    if (!(e instanceof HttpError)) return false;
    if (e.status === 413) return true;

    return (
        e.status === 422 &&
        Object.keys(e.errors ?? {}).some((key) => key === 'attachments' || key.startsWith('attachments.'))
    );
}

function error(kind: AssistantErrorKind, message: string | null = null): AssistantError {
    return { kind, message };
}

function describeHttpError(e: HttpError): AssistantError {
    if (e.status === 422) {
        const ai = e.errors?.ai?.[0];
        if (ai !== undefined) {
            return error('ai', ai);
        }

        return error('validation', Object.values(e.errors ?? {})[0]?.[0] ?? e.serverMessage);
    }
    if (e.status === 413) {
        return error('too_large');
    }
    if (e.status === 429) {
        return error('rate');
    }
    if (e.status === 403) {
        return error('forbidden', e.serverMessage);
    }
    if (e.status === 401 || e.status === 419) {
        return error('session');
    }
    if (e.status >= 500) {
        return error('outcome_unknown');
    }

    return error('other', e.serverMessage);
}

export function describeAssistantError(e: unknown): AssistantError {
    if (e instanceof HttpError) {
        return describeHttpError(e);
    }
    if (
        e instanceof TypeError ||
        (e instanceof DOMException && (e.name === 'AbortError' || e.name === 'TimeoutError'))
    ) {
        return error('outcome_unknown');
    }

    return error('other');
}

export function isAllowedAssistantLink(url: string | null): url is string {
    return url !== null && LINK_ALLOWLIST.test(url);
}
