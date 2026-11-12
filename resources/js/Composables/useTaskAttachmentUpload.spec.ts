import { beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { router } from '@inertiajs/vue3';

import { useTaskAttachmentUpload } from './useTaskAttachmentUpload';

const toastSuccess = vi.fn();
type BeforeEvent = { detail: { visit: { method: string; url: URL; async: boolean; only: string[] } } };
let beforeListener: ((event: BeforeEvent) => boolean | void) | null = null;

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, unknown>) => (params ? `${key}:${JSON.stringify(params)}` : key),
    }),
}));
vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ success: toastSuccess }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: vi.fn(),
        reload: vi.fn(),
        on: vi.fn((event: string, listener: (e: BeforeEvent) => boolean | void) => {
            if (event === 'before') {
                beforeListener = listener;
            }

            return vi.fn();
        }),
    },
}));

type Options = Record<string, (...args: never[]) => unknown>;

const constraints: App.Data.Tasks.TaskAttachmentConstraintsData = {
    max_size_kb: 10,
    max_count: 20,
    allowed_mimes: ['image/jpeg', 'application/pdf'],
    allowed_extensions: ['jpg', 'pdf'],
};

function listOf(count = 0): App.Data.Tasks.TaskAttachmentListData {
    return {
        attachments: Array.from({ length: count }, (_, i) => ({ id: `a${i}` })) as never,
        can_add: true,
        constraints,
    };
}

function pdf(name: string): File {
    return new File([new Uint8Array(4)], name, { type: 'application/pdf' });
}

function setup(existing = 0) {
    const scope = effectScope();
    const api = scope.run(() => useTaskAttachmentUpload({ taskId: 't1', list: () => listOf(existing) }))!;

    return { api, scope };
}

const post = router.post as unknown as ReturnType<typeof vi.fn>;
const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function optionsOf(call: number): Options {
    return post.mock.calls[call][2] as Options;
}

function finish(call: number, handlers: (options: Options) => void) {
    const options = optionsOf(call);
    handlers(options);
    (options.onFinish as () => void)();
}

beforeEach(() => {
    vi.clearAllMocks();
    beforeListener = null;
});

describe('useTaskAttachmentUpload', () => {
    it('posts files one at a time in order with field file', async () => {
        const { api } = setup();
        api.add([pdf('a.pdf'), pdf('b.pdf')]);

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/tasks/t1/attachments');
        expect(optionsOf(0).only).toEqual(['selected_attachments', 'flash']);
        expect((post.mock.calls[0][1] as { file: File }).file.name).toBe('a.pdf');
        expect(api.uploading.value).toBe(true);

        finish(0, (o) => (o.onSuccess as () => void)());
        await flush();

        expect(post).toHaveBeenCalledTimes(2);
        expect((post.mock.calls[1][1] as { file: File }).file.name).toBe('b.pdf');
    });

    it('continues after a validation error and toasts only the success count', async () => {
        const { api } = setup();
        api.add([pdf('a.pdf'), pdf('b.pdf')]);

        finish(0, (o) => (o.onError as (e: Record<string, string>) => void)({ file: 'bad' }));
        await flush();
        finish(1, (o) => (o.onSuccess as () => void)());
        await flush();

        expect(api.items.value).toHaveLength(1);
        expect(api.items.value[0]).toMatchObject({ name: 'a.pdf', status: 'failed', error: 'bad' });
        expect(toastSuccess).toHaveBeenCalledTimes(1);
        expect(toastSuccess).toHaveBeenCalledWith('task_attachment_uploaded:{"count":1}');
    });

    it('maps 413 to file_too_large and returns false from onHttpException', async () => {
        const { api } = setup();
        api.add([pdf('a.pdf')]);

        let result: unknown;
        finish(0, (o) => {
            result = (o.onHttpException as (r: { status: number }) => unknown)({ status: 413 });
        });
        await flush();

        expect(result).toBe(false);
        expect(api.items.value[0].error).toContain('file_too_large');
        expect(toastSuccess).not.toHaveBeenCalled();
    });

    it('marks the row on a network error and reloads attachments after the queue', async () => {
        const { api } = setup();
        api.add([pdf('a.pdf')]);

        let result: unknown;
        finish(0, (o) => {
            result = (o.onNetworkError as () => unknown)();
        });
        await flush();

        expect(result).toBe(false);
        expect(api.items.value[0].error).toBe('task_attachment_unknown_result');
        expect(router.reload).toHaveBeenCalledWith({ only: ['selected_attachments', 'flash'] });
    });

    it('stops the queue on cancel', async () => {
        const { api } = setup();
        api.add([pdf('a.pdf'), pdf('b.pdf')]);

        finish(0, (o) => (o.onCancel as () => void)());
        await flush();

        expect(post).toHaveBeenCalledTimes(1);
        expect(api.items.value.map((item) => item.status)).toEqual(['failed', 'failed']);
        expect(api.uploading.value).toBe(false);
    });

    it('fails surplus files with the limit hint', () => {
        const { api } = setup(19);
        api.add([pdf('a.pdf'), pdf('b.pdf'), pdf('c.pdf')]);

        expect(post).toHaveBeenCalledTimes(1);
        const failed = api.items.value.filter((item) => item.status === 'failed');

        expect(failed).toHaveLength(2);
        expect(failed[0].error).toBe('task_attachment_limit_hint:{"max":20}');
    });

    describe('leave guard during an upload', () => {
        const confirm = vi.fn(() => false);
        const visit = (
            method: string,
            path: string,
            extra: { async?: boolean; only?: string[] } = {},
        ): BeforeEvent => ({
            detail: { visit: { method, url: new URL(path, 'http://localhost'), async: false, only: [], ...extra } },
        });

        function uploadingApi() {
            vi.stubGlobal('confirm', confirm);
            window.confirm = confirm;
            confirm.mockClear();
            const { api } = setup();
            api.add([pdf('a.pdf')]);
        }

        it('does not ask without an upload', () => {
            setup();

            expect(beforeListener!(visit('get', '/clients'))).toBeUndefined();
        });

        it('does not ask for its own upload visits', () => {
            uploadingApi();

            expect(beforeListener!(visit('post', '/tasks/t1/attachments'))).toBeUndefined();
            expect(confirm).not.toHaveBeenCalled();
        });

        it('lets an async partial reload pass without asking', () => {
            uploadingApi();

            expect(
                beforeListener!(visit('get', '/tasks/t1', { async: true, only: ['selected_thread', 'flash'] })),
            ).toBeUndefined();
            expect(confirm).not.toHaveBeenCalled();
        });

        it('still asks for a sync navigation and a sync POST elsewhere', () => {
            uploadingApi();

            expect(beforeListener!(visit('get', '/clients'))).toBe(false);
            expect(beforeListener!(visit('post', '/tasks/t1/comments', { only: ['selected_thread', 'flash'] }))).toBe(
                false,
            );
            expect(confirm).toHaveBeenCalledTimes(2);
        });
    });
});
