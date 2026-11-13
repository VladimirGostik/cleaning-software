import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import type { App } from 'vue';

const submit = vi.fn();
const pending: (() => void)[] = [];
let formState: Record<string, unknown>;
let formArgs: unknown[] = [];

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (...args: unknown[]) => {
        formArgs = args;
        formState = reactive({ ...(args[2] as object), errors: {}, processing: false, submit });

        return formState;
    },
}));
vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ error: vi.fn() }) }));
vi.mock('@/Composables/useTaskInlineEdit', () => ({
    runAfterTaskEdits: (action: () => void) => {
        pending.push(action);

        return Promise.resolve();
    },
}));

import TaskCommentEditForm from './TaskCommentEditForm.vue';

let app: App | null = null;

const comment = { id: 'c9', body: 'Pôvodný text' } as App.Data.Tasks.TaskCommentData;

function mountForm() {
    const onDone = vi.fn();
    const parentKeydown = vi.fn();
    const host = document.createElement('div');
    host.addEventListener('keydown', parentKeydown);
    document.body.appendChild(host);
    app = createApp({
        render: () => h(TaskCommentEditForm, { taskId: 't1', comment, maxChars: 100, disabled: false, onDone }),
    });
    app.mount(host);

    return { host, onDone, parentKeydown };
}

async function type(host: HTMLElement, value: string) {
    const textarea = host.querySelector('textarea')!;
    textarea.value = value;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();

    return textarea;
}

function saveButton(host: HTMLElement) {
    return host.querySelector('button[type="submit"]')!;
}

describe('TaskCommentEditForm', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
        submit.mockClear();
        pending.length = 0;
    });

    it('starts with the comment body, puts to the comment url and keeps save disabled while unchanged', () => {
        const { host } = mountForm();

        expect(formArgs.slice(0, 2)).toEqual(['put', '/tasks/t1/comments/c9']);
        expect(host.querySelector('textarea')!.value).toBe('Pôvodný text');
        expect(saveButton(host).hasAttribute('disabled')).toBe(true);
    });

    it('disables save for empty and over-limit text', async () => {
        const { host } = mountForm();

        await type(host, '   ');
        expect(saveButton(host).hasAttribute('disabled')).toBe(true);

        await type(host, 'a'.repeat(101));
        expect(saveButton(host).hasAttribute('disabled')).toBe(true);

        await type(host, 'Nový text');
        expect(saveButton(host).hasAttribute('disabled')).toBe(false);
    });

    it('saves on Ctrl+Enter with the thread-only reload and emits done on success', async () => {
        const { host, onDone } = mountForm();
        const textarea = await type(host, 'Nový text');

        textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }));
        pending.shift()!();

        expect(submit).toHaveBeenCalledTimes(1);
        const options = submit.mock.calls[0]![0] as { only: string[]; onSuccess: () => void };
        expect(options.only).toEqual(['selected_thread', 'flash']);
        options.onSuccess();
        expect(onDone).toHaveBeenCalledTimes(1);
    });

    it('cancels on Escape without reaching a parent keydown listener', async () => {
        const { host, onDone, parentKeydown } = mountForm();

        host.querySelector('textarea')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(onDone).toHaveBeenCalledTimes(1);
        expect(parentKeydown).not.toHaveBeenCalled();
    });

    it('stops Escape from reaching the panel only because it is an edit form', () => {
        const { host, parentKeydown } = mountForm();
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });

        host.querySelector('textarea')!.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(parentKeydown).not.toHaveBeenCalled();
    });

    it('shows the 422 body error under the box', async () => {
        const { host } = mountForm();
        (formState.errors as Record<string, string>).body = 'Chyba';
        await nextTick();

        expect(host.querySelector('[role="alert"]')?.textContent).toBe('Chyba');
    });

    it('hands the save to the flush-before-write helper instead of submitting at once', async () => {
        const { host } = mountForm();
        await type(host, 'Nový text');

        host.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(pending).toHaveLength(1);
        expect(submit).not.toHaveBeenCalled();

        pending[0]!();
        expect(submit).toHaveBeenCalledTimes(1);
    });
});
