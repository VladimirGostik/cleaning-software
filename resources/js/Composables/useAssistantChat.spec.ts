import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HttpError, getJson, postForm } from '@/utils/http';
import { rememberConversation, rememberedConversation } from '@/utils/assistant';
import { FILES_REQUEST_TIMEOUT_MS, REQUEST_TIMEOUT_MS } from '@/utils/assistant';
import { useAssistantChat } from './useAssistantChat';

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    postForm: vi.fn(),
    getJson: vi.fn(),
}));

const post = vi.mocked(postForm);
const get = vi.mocked(getJson);

function reply(overrides: Partial<App.Data.Ai.AssistantReplyData> = {}): App.Data.Ai.AssistantReplyData {
    return {
        reply: 'Hello',
        actions: [],
        pending_action: null,
        security_notice: null,
        attachment_note: null,
        conversation_id: 'conv1',
        conversation_title: 'Title',
        ...overrides,
    };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<T>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

function entries(form: unknown): [string, string][] {
    return Array.from((form as FormData).entries()).map(([key, value]) => [
        key,
        value instanceof File ? `file:${value.name}` : value,
    ]);
}

function sentForm(call = 0): FormData {
    return post.mock.calls[call][1];
}

function pdf(name: string): File {
    return new File([new Uint8Array(4)], name, { type: 'application/pdf' });
}

const chat = useAssistantChat();

beforeEach(() => {
    window.sessionStorage.clear();
    post.mockReset();
    get.mockReset();
    chat.reset();
    chat.close();
    chat.bind('u1', 'c1');
});

describe('useAssistantChat', () => {
    it('pushes the user turn before the request resolves and appends the reply', async () => {
        const pending = deferred<App.Data.Ai.AssistantReplyData>();
        post.mockReturnValueOnce(pending.promise);

        const sent = chat.send('  Hi  ');

        expect(chat.state.messages.map((m) => [m.role, m.content])).toEqual([['user', 'Hi']]);
        expect(chat.state.sending).toBe(true);
        expect(post.mock.calls[0][0]).toBe('/assistant/messages');
        expect(sentForm().get('content')).toBe('Hi');
        expect(sentForm().has('conversation_id')).toBe(false);
        expect(sentForm().get('client_message_id')).toMatch(/^[0-9a-f-]{36}$/);

        pending.resolve(reply({ reply: 'Hey', security_notice: 'careful' }));
        await sent;

        expect(chat.state.sending).toBe(false);
        expect(chat.state.messages.at(-1)).toMatchObject({
            role: 'assistant',
            content: 'Hey',
            securityNotice: 'careful',
        });
    });

    it('ignores empty text and a second send while sending', async () => {
        post.mockReturnValue(deferred<App.Data.Ai.AssistantReplyData>().promise);

        await chat.send('   ');
        void chat.send('one');
        await chat.send('two');

        expect(post).toHaveBeenCalledTimes(1);
        expect(chat.state.messages).toHaveLength(1);
    });

    it('keeps a late reply after a company change', async () => {
        const pending = deferred<App.Data.Ai.AssistantReplyData>();
        post.mockReturnValueOnce(pending.promise);

        const sent = chat.send('Hi');
        chat.bind('u1', 'c2');
        pending.resolve(reply());
        await sent;

        expect(chat.state.messages).toHaveLength(2);
        expect(chat.state.sending).toBe(false);
    });

    it('aborts the in-flight request on reset without surfacing an error', async () => {
        const pending = deferred<App.Data.Ai.AssistantReplyData>();
        post.mockReturnValueOnce(pending.promise);

        const sent = chat.send('Hi');
        const signal = post.mock.calls[0][2]!.signal!;
        chat.reset();
        pending.reject(new DOMException('aborted', 'AbortError'));
        await sent;

        expect(signal.aborted).toBe(true);
        expect(chat.state.error).toBeNull();
        expect(chat.state.blocked).toBe(false);
    });

    it('reports a timeout as an unknown outcome and blocks', async () => {
        vi.useFakeTimers();
        try {
            post.mockImplementationOnce(
                (_url, _body, opts) =>
                    new Promise((_resolve, reject) => {
                        opts!.signal!.addEventListener('abort', () => reject(opts!.signal!.reason));
                    }),
            );

            const sent = chat.send('Hi');
            await vi.advanceTimersByTimeAsync(REQUEST_TIMEOUT_MS);
            await sent;

            expect(chat.state.error?.kind).toBe('outcome_unknown');
            expect(chat.state.blocked).toBe(true);
        } finally {
            vi.useRealTimers();
        }
    });

    it('keeps the conversation across a company switch and sends the open company', async () => {
        post.mockResolvedValue(reply());
        await chat.send('Hi');

        chat.bind('u1', 'c2');
        expect(chat.state.messages).toHaveLength(2);

        await chat.send('And firm B?');
        const form = sentForm(post.mock.calls.length - 1);
        expect(form.get('company_id')).toBe('c2');
        expect(form.get('conversation_id')).toBe('conv1');
        expect(form.get('content')).toBe('And firm B?');
    });

    it('starts over for another user', async () => {
        post.mockResolvedValue(reply());
        await chat.send('Hi');

        chat.bind('u2', 'c1');
        expect(chat.state.messages).toEqual([]);
    });

    it('drops a late reply of the previous user', async () => {
        const pending = deferred<App.Data.Ai.AssistantReplyData>();
        post.mockReturnValueOnce(pending.promise);

        const sent = chat.send('Hi');
        chat.bind('u2', 'c1');
        pending.resolve(reply());
        await sent;

        expect(chat.state.messages).toEqual([]);
    });

    it('blocks after an unknown outcome, and retry clears it and re-posts the same history', async () => {
        post.mockRejectedValueOnce(new HttpError(504)).mockResolvedValueOnce(reply());

        await chat.send('Hi');

        expect(chat.state.blocked).toBe(true);
        expect(chat.state.error?.kind).toBe('outcome_unknown');

        await chat.send('again');
        expect(post).toHaveBeenCalledTimes(1);

        await chat.retry();

        expect(chat.state.blocked).toBe(false);
        expect(chat.state.error).toBeNull();
        expect(sentForm(1).get('client_message_id')).toBe(sentForm(0).get('client_message_id'));
        expect(entries(sentForm(1))).toEqual(entries(sentForm(0)));
        expect(chat.state.messages.at(-1)?.role).toBe('assistant');
    });

    it('does not block on a definite failure', async () => {
        post.mockRejectedValueOnce(new HttpError(429, 5));

        await chat.send('Hi');

        expect(chat.state.blocked).toBe(false);
        expect(chat.state.error?.kind).toBe('rate');
    });

    it('acknowledge unblocks without re-posting', async () => {
        post.mockRejectedValueOnce(new HttpError(500));
        await chat.send('Hi');

        chat.acknowledge();

        expect(chat.state.blocked).toBe(false);
        expect(chat.state.error).toBeNull();
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('records the outcome of a prepared action on its message', async () => {
        const pending = { id: 'p1' } as App.Data.Ai.PendingActionData;
        post.mockResolvedValueOnce(reply({ pending_action: pending }));
        await chat.send('Hi');

        chat.settlePending('p1', 'done');

        expect(chat.state.messages.at(-1)?.pendingOutcome).toBe('done');
    });

    describe('conversation', () => {
        it('stores id and title from the reply, remembers them and continues the conversation', async () => {
            post.mockResolvedValue(reply());

            await chat.send('Hi');

            expect(chat.state.conversationId).toBe('conv1');
            expect(chat.state.title).toBe('Title');
            expect(rememberedConversation('u1')).toBe('conv1');

            await chat.send('more');

            expect(sentForm(1).get('conversation_id')).toBe('conv1');
        });

        it('resets on a 422 for conversation_id and returns text and files', async () => {
            const file = pdf('a.pdf');
            post.mockResolvedValueOnce(reply());
            await chat.send('Hi');
            post.mockRejectedValueOnce(new HttpError(422, null, null, { conversation_id: ['gone'] }));

            await expect(chat.send('again', [file])).resolves.toBe('returned');

            expect(chat.state.messages).toEqual([]);
            expect(chat.state.conversationId).toBeNull();
            expect(chat.state.error).toBeNull();
            expect(rememberedConversation('u1')).toBeNull();
            expect(chat.takeReturned()).toMatchObject({
                text: 'again',
                files: [file],
                error: { kind: 'conversation_gone' },
            });
        });

        it('load maps messages with the live pending status', async () => {
            get.mockResolvedValueOnce({
                id: 'conv9',
                title: 'Old',
                last_message_at: '2026-11-13T10:00:00Z',
                messages: [
                    {
                        id: 'a',
                        role: 'assistant',
                        content: 'x',
                        attachment_names: [],
                        actions: [],
                        pending_action: { id: 'p1', status: 'executed' },
                        security_notice: null,
                        attachment_note: null,
                        created_at: '2026-11-13T10:00:00Z',
                    },
                ],
            });

            await chat.load('conv9');

            expect(chat.state.conversationId).toBe('conv9');
            expect(chat.state.title).toBe('Old');
            expect(chat.state.loading).toBe(false);
            expect(chat.state.messages[0].pendingOutcome).toBe('done');
            expect(rememberedConversation('u1')).toBe('conv9');
        });

        it('load aborts an in-flight send and drops its late reply', async () => {
            const pending = deferred<App.Data.Ai.AssistantReplyData>();
            post.mockReturnValueOnce(pending.promise);
            get.mockResolvedValueOnce({ id: 'conv9', title: 'Old', last_message_at: '', messages: [] });

            const sent = chat.send('Hi');
            await chat.load('conv9');
            pending.resolve(reply());
            await sent;

            expect(chat.state.sending).toBe(false);
            expect(chat.state.messages).toEqual([]);
            expect(chat.state.conversationId).toBe('conv9');
        });

        it('load starts new and forgets on 404, flags loadError on 500', async () => {
            rememberConversation('u1', 'gone');
            get.mockRejectedValueOnce(new HttpError(404));
            await chat.load('gone');

            expect(chat.state.conversationId).toBeNull();
            expect(rememberedConversation('u1')).toBeNull();
            expect(chat.state.loadError).toBe(false);

            get.mockRejectedValueOnce(new HttpError(500));
            await chat.load('x');

            expect(chat.state.loadError).toBe(true);
            expect(chat.state.loading).toBe(false);
        });

        it('restoreRemembered loads only an empty chat with a remembered id of this user', async () => {
            rememberConversation('u2', 'other');
            await chat.restoreRemembered();
            expect(get).not.toHaveBeenCalled();

            rememberConversation('u1', 'mine');
            get.mockResolvedValue({ id: 'mine', title: 'T', last_message_at: '', messages: [] });
            await chat.restoreRemembered();
            expect(get).toHaveBeenCalledTimes(1);

            await chat.restoreRemembered();
            expect(get).toHaveBeenCalledTimes(1);
        });

        it('startNew forgets the memory, reset does not', async () => {
            post.mockResolvedValue(reply());
            await chat.send('Hi');

            chat.reset();
            expect(rememberedConversation('u1')).toBe('conv1');
            expect(chat.state.conversationId).toBeNull();

            chat.startNew();
            expect(rememberedConversation('u1')).toBeNull();
        });

        it('retitle and forgetIfOpen act only on the open conversation', async () => {
            post.mockResolvedValue(reply());
            await chat.send('Hi');

            chat.retitle('other', 'X');
            expect(chat.state.title).toBe('Title');
            chat.retitle('conv1', 'New');
            expect(chat.state.title).toBe('New');

            chat.forgetIfOpen('other');
            expect(chat.state.conversationId).toBe('conv1');
            chat.forgetIfOpen('conv1');
            expect(chat.state.conversationId).toBeNull();
            expect(chat.state.messages).toEqual([]);
        });
    });

    describe('files', () => {
        it('a message with files sends the files', async () => {
            post.mockResolvedValueOnce(reply());

            const outcome = await chat.send('', [pdf('a.pdf'), pdf('b.pdf')]);

            expect(outcome).toBe('replied');
            expect(entries(sentForm()).filter(([key]) => key === 'attachments[]')).toEqual([
                ['attachments[]', 'file:a.pdf'],
                ['attachments[]', 'file:b.pdf'],
            ]);
            expect(sentForm().get('content')).toBe('');
            expect(chat.state.messages[0].attachmentNames).toEqual(['a.pdf', 'b.pdf']);
        });

        it('sends no files on the next turn', async () => {
            post.mockResolvedValue(reply({ attachment_note: 'File saved to: expense.' }));
            await chat.send('', [pdf('a.pdf')]);

            expect(chat.state.messages.at(-1)?.attachmentNote).toBe('File saved to: expense.');

            await chat.send('thanks');

            const form = sentForm(1);
            expect(form.has('attachments[]')).toBe(false);
            expect(form.get('content')).toBe('thanks');
        });

        it('retry resends the held files after a network error', async () => {
            post.mockRejectedValueOnce(new TypeError('network')).mockResolvedValueOnce(reply());

            await chat.send('see', [pdf('a.pdf')]);
            expect(chat.state.blocked).toBe(true);

            await expect(chat.retry()).resolves.toBe('replied');

            expect(entries(sentForm(1))).toEqual(entries(sentForm(0)));
        });

        it('hands the message back on a 422 for attachments and removes the user turn', async () => {
            const file = pdf('a.pdf');
            post.mockRejectedValueOnce(new HttpError(422, null, null, { 'attachments.0': ['Too many pages'] }));

            const outcome = await chat.send('check', [file]);

            expect(outcome).toBe('returned');
            expect(chat.state.messages).toEqual([]);
            expect(chat.state.error).toBeNull();
            expect(chat.takeReturned()).toMatchObject({
                text: 'check',
                files: [file],
                error: { kind: 'validation', message: 'Too many pages' },
            });
            expect(chat.takeReturned()).toBeNull();
        });

        it('keeps the returned message in state until a consumer takes it', async () => {
            const file = pdf('a.pdf');
            post.mockRejectedValueOnce(new HttpError(413));

            await chat.send('later', [file]);

            expect(chat.returned.value).toMatchObject({ text: 'later', files: [file] });
            expect(chat.takeReturned()?.text).toBe('later');
            expect(chat.returned.value).toBeNull();
        });

        it('waits 120 s for a turn with files and 90 s for text only', async () => {
            vi.useFakeTimers();
            try {
                const hang = (_url: string, _body: FormData, opts?: { signal?: AbortSignal }) =>
                    new Promise<never>((_resolve, reject) => {
                        opts!.signal!.addEventListener('abort', () => reject(opts!.signal!.reason));
                    });
                post.mockImplementationOnce(hang);

                const sent = chat.send('see', [pdf('a.pdf')]);
                await vi.advanceTimersByTimeAsync(REQUEST_TIMEOUT_MS);
                expect(chat.state.sending).toBe(true);
                await vi.advanceTimersByTimeAsync(FILES_REQUEST_TIMEOUT_MS - REQUEST_TIMEOUT_MS);
                await sent;

                expect(chat.state.error?.kind).toBe('outcome_unknown');
            } finally {
                vi.useRealTimers();
            }
        });

        it('hands the message back on a 413 with the too_large kind', async () => {
            post.mockRejectedValueOnce(new HttpError(413));

            await expect(chat.send('x', [pdf('a.pdf')])).resolves.toBe('returned');

            expect(chat.takeReturned()?.error.kind).toBe('too_large');
        });

        it('keeps the turn on a 422 that is not about the files', async () => {
            post.mockRejectedValueOnce(new HttpError(422, null, null, { messages: ['Too long'] }));

            await expect(chat.send('x', [pdf('a.pdf')])).resolves.toBe('failed');

            expect(chat.state.messages).toHaveLength(1);
            expect(chat.state.error?.kind).toBe('validation');
        });

        it('reset drops the held files', async () => {
            const pending = deferred<App.Data.Ai.AssistantReplyData>();
            post.mockReturnValueOnce(pending.promise);
            const sent = chat.send('x', [pdf('a.pdf')]);

            chat.reset();
            pending.reject(new DOMException('aborted', 'AbortError'));
            await sent;
            await expect(chat.retry()).resolves.toBe('skipped');

            post.mockResolvedValueOnce(reply());
            await chat.send('plain');

            expect(sentForm(1).has('attachments[]')).toBe(false);
        });

        it('skips an empty text without files', async () => {
            await expect(chat.send('  ')).resolves.toBe('skipped');
            expect(post).not.toHaveBeenCalled();
        });
    });
});
