import type { ChatMessage, PendingOutcome } from '@/Composables/useAssistantChat';
import { HttpError } from '@/utils/http';

export const MAX_MESSAGE_CHARS = 4000;
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
    | 'conversation_gone'
    | 'other';

export interface AssistantError {
    kind: AssistantErrorKind;
    message: string | null;
}

const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
const LINK_ALLOWLIST = new RegExp(`^/((tasks|invoices|quotes|expenses)/${UUID}|pending-actions)$`, 'i');
const MEMORY_PREFIX = 'kg:assistant:conversation:';

export interface AssistantSendInput {
    companyId: string;
    conversationId: string | null;
    clientMessageId: string;
    content: string;
    files: readonly File[];
}

export interface AssistantConversationPage {
    data: App.Data.Ai.AssistantConversationItemData[];
    meta: { current_page: number; last_page: number };
}

export function buildAssistantFormData(input: AssistantSendInput): FormData {
    const form = new FormData();
    form.append('company_id', input.companyId);
    if (input.conversationId !== null) form.append('conversation_id', input.conversationId);
    form.append('client_message_id', input.clientMessageId);
    form.append('content', input.content);
    input.files.forEach((file) => form.append('attachments[]', file));

    return form;
}

export function pendingOutcomeOf(action: App.Data.Ai.PendingActionData | null): PendingOutcome | null {
    if (action?.status === 'executed') return 'done';
    if (action?.status === 'cancelled') return 'cancelled';

    return null;
}

export function toChatMessages(messages: readonly App.Data.Ai.AssistantHistoryMessageData[]): ChatMessage[] {
    return messages.map((message) => ({
        id: message.id,
        role: message.role,
        content: message.content,
        actions: message.actions,
        pending: message.pending_action,
        pendingOutcome: pendingOutcomeOf(message.pending_action),
        securityNotice: message.security_notice,
        attachmentNames: message.attachment_names,
        attachmentNote: message.attachment_note,
        clientMessageId: null,
    }));
}

export function isConversationGone(e: unknown): boolean {
    return e instanceof HttpError && e.status === 422 && (e.errors?.conversation_id?.length ?? 0) > 0;
}

function memory(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
}

export function rememberConversation(userId: string, conversationId: string): void {
    try {
        memory()?.setItem(MEMORY_PREFIX + userId, conversationId);
    } catch {
        return;
    }
}

export function rememberedConversation(userId: string): string | null {
    try {
        return memory()?.getItem(MEMORY_PREFIX + userId) ?? null;
    } catch {
        return null;
    }
}

export function forgetConversation(userId: string): void {
    try {
        memory()?.removeItem(MEMORY_PREFIX + userId);
    } catch {
        return;
    }
}

export function forgetAllConversations(): void {
    try {
        const storage = memory();
        if (storage === null) return;
        const keys: string[] = [];
        for (let i = 0; i < storage.length; i++) {
            const key = storage.key(i);
            if (key?.startsWith(MEMORY_PREFIX)) keys.push(key);
        }
        keys.forEach((key) => storage.removeItem(key));
    } catch {
        return;
    }
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
