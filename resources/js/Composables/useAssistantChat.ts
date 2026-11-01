import { reactive, readonly, type DeepReadonly } from 'vue';
import { postJson } from '@/utils/http';
import {
    REQUEST_TIMEOUT_MS,
    buildPayloadMessages,
    describeAssistantError,
    type AssistantError,
} from '@/utils/assistant';

export type PendingOutcome = 'done' | 'cancelled';

export interface ChatMessage {
    id: string;
    role: App.Enums.AssistantRoleEnum;
    content: string;
    actions: App.Data.Ai.AssistantActionData[];
    pending: App.Data.Ai.PendingActionData | null;
    pendingOutcome: PendingOutcome | null;
    securityNotice: string | null;
}

export interface AssistantChatState {
    open: boolean;
    messages: ChatMessage[];
    sending: boolean;
    error: AssistantError | null;
    blocked: boolean;
    userId: string | null;
    companyId: string | null;
}

const ENDPOINT = '/assistant/messages';

const state = reactive<AssistantChatState>({
    open: false,
    messages: [],
    sending: false,
    error: null,
    blocked: false,
    userId: null,
    companyId: null,
});

let epoch = 0;
let sequence = 0;
let controller: AbortController | null = null;

function turn(role: App.Enums.AssistantRoleEnum, content: string, reply?: App.Data.Ai.AssistantReplyData): ChatMessage {
    return {
        id: `m${++sequence}`,
        role,
        content,
        actions: reply?.actions ?? [],
        pending: reply?.pending_action ?? null,
        pendingOutcome: null,
        securityNotice: reply?.security_notice ?? null,
    };
}

async function dispatch(companyId: string): Promise<void> {
    const mine = epoch;
    const own = new AbortController();
    controller = own;
    const timeout = setTimeout(() => own.abort(new DOMException('timeout', 'TimeoutError')), REQUEST_TIMEOUT_MS);
    state.sending = true;
    state.error = null;

    try {
        const reply = await postJson<App.Data.Ai.AssistantReplyData>(
            ENDPOINT,
            { company_id: companyId, messages: buildPayloadMessages(state.messages) },
            { signal: own.signal },
        );
        if (mine !== epoch) return;
        state.messages.push(turn('assistant', reply.reply, reply));
    } catch (e) {
        if (mine !== epoch) return;
        state.error = describeAssistantError(e);
        state.blocked = state.error.kind === 'outcome_unknown';
    } finally {
        clearTimeout(timeout);
        if (mine === epoch) state.sending = false;
    }
}

function send(text: string): Promise<void> {
    const content = text.trim();
    if (content === '' || state.companyId === null || state.sending || state.blocked) return Promise.resolve();

    state.messages.push(turn('user', content));

    return dispatch(state.companyId);
}

function retry(): Promise<void> {
    const last = state.messages.at(-1);
    if (state.sending || state.companyId === null || last?.role !== 'user') return Promise.resolve();

    state.blocked = false;

    return dispatch(state.companyId);
}

function acknowledge(): void {
    state.blocked = false;
    state.error = null;
}

function reset(): void {
    epoch += 1;
    controller?.abort();
    controller = null;
    state.messages = [];
    state.sending = false;
    state.error = null;
    state.blocked = false;
}

function bind(userId: string, companyId: string): boolean {
    if (state.userId === userId && state.companyId === companyId) return false;

    const wasInFlight = state.sending;
    reset();
    state.userId = userId;
    state.companyId = companyId;

    return wasInFlight;
}

function closeOnSmallScreen(): void {
    if (!window.matchMedia('(min-width: 40rem)').matches) close();
}

function settlePending(actionId: string, outcome: PendingOutcome): void {
    const message = state.messages.find((m) => m.pending?.id === actionId);
    if (message) message.pendingOutcome = outcome;
}

function toggle(): void {
    state.open = !state.open;
}

function close(): void {
    state.open = false;
}

export function useAssistantChat() {
    return {
        state: readonly(state) as DeepReadonly<AssistantChatState>,
        send,
        retry,
        acknowledge,
        reset,
        bind,
        settlePending,
        toggle,
        close,
        closeOnSmallScreen,
    };
}
