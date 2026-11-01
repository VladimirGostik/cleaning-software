import { HttpError } from '@/utils/http';

export const MAX_MESSAGES = 20;
export const MAX_MESSAGE_CHARS = 4000;
export const MAX_TOTAL_CHARS = 12000;
export const REQUEST_TIMEOUT_MS = 90_000;

export type AssistantErrorKind = 'ai' | 'validation' | 'rate' | 'forbidden' | 'session' | 'outcome_unknown' | 'other';

export interface AssistantError {
    kind: AssistantErrorKind;
    message: string | null;
}

interface Turn {
    role: App.Enums.AssistantRoleEnum;
    content: string;
}

const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
const LINK_ALLOWLIST = new RegExp(`^/((tasks|invoices|quotes|expenses)/${UUID}|pending-actions)$`, 'i');

function total(turns: Turn[]): number {
    return turns.reduce((sum, turn) => sum + turn.content.length, 0);
}

export function buildPayloadMessages(history: readonly Turn[]): App.Data.Ai.AssistantMessageData[] {
    let turns = history
        .filter((turn) => turn.content.trim() !== '')
        .map((turn): Turn => ({ role: turn.role, content: turn.content.slice(0, MAX_MESSAGE_CHARS) }))
        .slice(-MAX_MESSAGES);

    while (turns.length > 1 && total(turns) > MAX_TOTAL_CHARS) {
        turns = turns.slice(1);
    }

    return turns;
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
