import { computed, reactive, readonly, shallowRef, type DeepReadonly } from 'vue';
import { postForm } from '@/utils/http';
import {
    FILES_REQUEST_TIMEOUT_MS,
    REQUEST_TIMEOUT_MS,
    buildAssistantFormData,
    buildPayloadMessages,
    describeAssistantError,
    isAttachmentRejection,
    type AssistantError,
} from '@/utils/assistant';

export type PendingOutcome = 'done' | 'cancelled';
export type SendOutcome = 'replied' | 'failed' | 'returned' | 'skipped';

export interface ReturnedMessage {
    text: string;
    files: File[];
    error: AssistantError;
}

export interface ChatMessage {
    id: string;
    role: App.Enums.AssistantRoleEnum;
    content: string;
    actions: App.Data.Ai.AssistantActionData[];
    pending: App.Data.Ai.PendingActionData | null;
    pendingOutcome: PendingOutcome | null;
    securityNotice: string | null;
    attachmentNames: string[];
    attachmentNote: string | null;
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
let heldFiles: File[] = [];
const returned = shallowRef<ReturnedMessage | null>(null);
const returnedMessage = computed(() => returned.value);

function turn(
    role: App.Enums.AssistantRoleEnum,
    content: string,
    reply?: App.Data.Ai.AssistantReplyData,
    attachmentNames: string[] = [],
): ChatMessage {
    return {
        id: `m${++sequence}`,
        role,
        content,
        actions: reply?.actions ?? [],
        pending: reply?.pending_action ?? null,
        pendingOutcome: null,
        securityNotice: reply?.security_notice ?? null,
        attachmentNames,
        attachmentNote: reply?.attachment_note ?? null,
    };
}

function history(files: readonly File[]) {
    const messages = buildPayloadMessages(state.messages);
    const last = messages.at(-1);
    if (files.length > 0 && last !== undefined) delete last.attachment_names;

    return messages;
}

function giveBack(content: string, files: File[], error: AssistantError): void {
    state.messages.pop();
    heldFiles = [];
    returned.value = { text: content, files, error };
}

async function dispatch(companyId: string): Promise<SendOutcome> {
    const mine = epoch;
    const own = new AbortController();
    controller = own;
    const files = heldFiles;
    const timeout = setTimeout(
        () => own.abort(new DOMException('timeout', 'TimeoutError')),
        files.length > 0 ? FILES_REQUEST_TIMEOUT_MS : REQUEST_TIMEOUT_MS,
    );
    state.sending = true;
    state.error = null;
    let outcome: SendOutcome = 'failed';

    try {
        const reply = await postForm<App.Data.Ai.AssistantReplyData>(
            ENDPOINT,
            buildAssistantFormData(companyId, history(files), files),
            { signal: own.signal },
        );
        if (mine !== epoch) return 'failed';
        heldFiles = [];
        state.messages.push(turn('assistant', reply.reply, reply));
        outcome = 'replied';
    } catch (e) {
        if (mine !== epoch) return 'failed';
        const error = describeAssistantError(e);
        const last = state.messages.at(-1);
        if (isAttachmentRejection(e) && last?.role === 'user') {
            giveBack(last.content, files, error);
            outcome = 'returned';
        } else {
            state.error = error;
            state.blocked = error.kind === 'outcome_unknown';
        }
    } finally {
        clearTimeout(timeout);
        if (mine === epoch) state.sending = false;
    }

    return outcome;
}

function send(text: string, files: File[] = []): Promise<SendOutcome> {
    const content = text.trim();
    if ((content === '' && files.length === 0) || state.companyId === null || state.sending || state.blocked) {
        return Promise.resolve('skipped');
    }

    returned.value = null;
    heldFiles = files;
    state.messages.push(
        turn(
            'user',
            content,
            undefined,
            files.map((file) => file.name),
        ),
    );

    return dispatch(state.companyId);
}

function retry(): Promise<SendOutcome> {
    const last = state.messages.at(-1);
    if (state.sending || state.companyId === null || last?.role !== 'user') return Promise.resolve('skipped');

    state.blocked = false;

    return dispatch(state.companyId);
}

function takeReturned(): ReturnedMessage | null {
    const message = returned.value;
    returned.value = null;

    return message;
}

function acknowledge(): void {
    state.blocked = false;
    state.error = null;
}

function reset(): void {
    epoch += 1;
    controller?.abort();
    controller = null;
    heldFiles = [];
    returned.value = null;
    state.messages = [];
    state.sending = false;
    state.error = null;
    state.blocked = false;
}

/** The assistant works across all of the user's companies: a company switch keeps the conversation, a new user starts over. */
function bind(userId: string, companyId: string): void {
    if (state.userId !== userId) reset();

    state.userId = userId;
    state.companyId = companyId;
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
        returned: returnedMessage,
        takeReturned,
        acknowledge,
        reset,
        bind,
        settlePending,
        toggle,
        close,
        closeOnSmallScreen,
    };
}
