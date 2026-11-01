import { beforeEach, describe, expect, it, vi } from 'vitest';
import { HttpError, postJson } from '@/utils/http';
import { REQUEST_TIMEOUT_MS } from '@/utils/assistant';
import { useAssistantChat } from './useAssistantChat';

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    postJson: vi.fn(),
}));

const post = vi.mocked(postJson);

function reply(overrides: Partial<App.Data.Ai.AssistantReplyData> = {}): App.Data.Ai.AssistantReplyData {
    return { reply: 'Hello', actions: [], pending_action: null, security_notice: null, ...overrides };
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

const chat = useAssistantChat();

beforeEach(() => {
    post.mockReset();
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
        expect(post.mock.calls[0][1]).toEqual({ company_id: 'c1', messages: [{ role: 'user', content: 'Hi' }] });

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

    it('drops a late reply after a company change and reports the request was in flight', async () => {
        const pending = deferred<App.Data.Ai.AssistantReplyData>();
        post.mockReturnValueOnce(pending.promise);

        const sent = chat.send('Hi');
        expect(chat.bind('u1', 'c2')).toBe(true);
        pending.resolve(reply());
        await sent;

        expect(chat.state.messages).toEqual([]);
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

    it('keeps the conversation when bound to the same user and company', async () => {
        post.mockResolvedValueOnce(reply());
        await chat.send('Hi');

        expect(chat.bind('u1', 'c1')).toBe(false);
        expect(chat.state.messages).toHaveLength(2);
    });

    it('resets on company change and on user change without any subscriber', async () => {
        post.mockResolvedValue(reply());

        await chat.send('Hi');
        chat.bind('u1', 'c2');
        expect(chat.state.messages).toEqual([]);

        await chat.send('Hi');
        chat.bind('u2', 'c2');
        expect(chat.state.messages).toEqual([]);
    });

    it('does not carry a conversation across company A -> B -> C', async () => {
        post.mockResolvedValue(reply());
        await chat.send('secret of A');

        chat.bind('u1', 'c2');
        chat.bind('u1', 'c3');
        await chat.send('hello C');

        expect(post.mock.calls.at(-1)![1]).toEqual({
            company_id: 'c3',
            messages: [{ role: 'user', content: 'hello C' }],
        });
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
        expect(post.mock.calls[1][1]).toEqual(post.mock.calls[0][1]);
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
});
