import { computed, reactive, readonly, shallowRef, type DeepReadonly } from 'vue';
import { HttpError, getJson, postForm } from '@/utils/http';
import {
    FILES_REQUEST_TIMEOUT_MS,
    REQUEST_TIMEOUT_MS,
    buildAssistantFormData,
    describeAssistantError,
    forgetConversation,
    isAttachmentRejection,
    isConversationGone,
    rememberConversation,
    rememberedConversation,
    toChatMessages,
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
    clientMessageId: string | null;
}

export interface AssistantChatState {
    open: boolean;
    messages: ChatMessage[];
    sending: boolean;
    error: AssistantError | null;
    blocked: boolean;
    userId: string | null;
    companyId: string | null;
    conversationId: string | null;
    title: string | null;
    loading: boolean;
    loadError: boolean;
}

const ENDPOINT = '/assistant/messages';
const CONVERSATIONS = '/assistant/conversations';

const state = reactive<AssistantChatState>({
    open: false,
    messages: [],
    sending: false,
    error: null,
    blocked: false,
    userId: null,
    companyId: null,
    conversationId: null,
    title: null,
    loading: false,
    loadError: false,
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
    clientMessageId: string | null = null,
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
        clientMessageId,
    };
}

function giveBack(content: string, files: File[], error: AssistantError): void {
    state.messages.pop();
    heldFiles = [];
    returned.value = { text: content, files, error };
}

function dropConversation(): void {
    const userId = state.userId;
    reset();
    if (userId !== null) forgetConversation(userId);
}

async function dispatch(companyId: string, user: ChatMessage): Promise<SendOutcome> {
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
            buildAssistantFormData({
                companyId,
                conversationId: state.conversationId,
                clientMessageId: user.clientMessageId ?? window.crypto.randomUUID(),
                content: user.content,
                files,
            }),
            { signal: own.signal },
        );
        if (mine !== epoch) return 'failed';
        heldFiles = [];
        state.conversationId = reply.conversation_id;
        state.title = reply.conversation_title;
        if (state.userId !== null) rememberConversation(state.userId, reply.conversation_id);
        state.messages.push(turn('assistant', reply.reply, reply));
        outcome = 'replied';
    } catch (e) {
        if (mine !== epoch) return 'failed';
        const error = describeAssistantError(e);
        const last = state.messages.at(-1);
        if (isAttachmentRejection(e) && last?.role === 'user') {
            giveBack(last.content, files, error);
            outcome = 'returned';
        } else if (isConversationGone(e) && last?.role === 'user') {
            const content = last.content;
            dropConversation();
            returned.value = { text: content, files, error: { kind: 'conversation_gone', message: null } };
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
    const user = turn(
        'user',
        content,
        undefined,
        files.map((file) => file.name),
        window.crypto.randomUUID(),
    );
    state.messages.push(user);

    return dispatch(state.companyId, user);
}

function retry(): Promise<SendOutcome> {
    const last = state.messages.at(-1);
    if (state.sending || state.companyId === null || last?.role !== 'user') return Promise.resolve('skipped');

    state.blocked = false;

    return dispatch(state.companyId, last);
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
    state.conversationId = null;
    state.title = null;
    state.loading = false;
    state.loadError = false;
}

function startNew(): void {
    dropConversation();
}

async function load(conversationId: string): Promise<void> {
    epoch += 1;
    const mine = epoch;
    controller?.abort();
    const own = new AbortController();
    controller = own;
    heldFiles = [];
    returned.value = null;
    state.sending = false;
    state.error = null;
    state.blocked = false;
    state.messages = [];
    state.conversationId = conversationId;
    state.title = null;
    state.loading = true;
    state.loadError = false;

    try {
        const conversation = await getJson<App.Data.Ai.AssistantConversationData>(
            `${CONVERSATIONS}/${conversationId}`,
            { signal: own.signal },
        );
        if (mine !== epoch) return;
        state.messages = toChatMessages(conversation.messages);
        state.conversationId = conversation.id;
        state.title = conversation.title;
        if (state.userId !== null) rememberConversation(state.userId, conversation.id);
    } catch (e) {
        if (mine !== epoch) return;
        if (e instanceof HttpError && e.status === 404) {
            startNew();
            return;
        }
        state.loadError = true;
    } finally {
        if (mine === epoch) state.loading = false;
    }
}

async function restoreRemembered(): Promise<void> {
    if (state.userId === null || state.messages.length > 0 || state.conversationId !== null) return;

    const id = rememberedConversation(state.userId);
    if (id !== null) await load(id);
}

function retitle(conversationId: string, title: string): void {
    if (state.conversationId === conversationId) state.title = title;
}

function forgetIfOpen(conversationId: string): void {
    if (state.conversationId === conversationId) startNew();
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
        startNew,
        load,
        restoreRemembered,
        retitle,
        forgetIfOpen,
        bind,
        settlePending,
        toggle,
        close,
        closeOnSmallScreen,
    };
}
