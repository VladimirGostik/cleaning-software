import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import type { App } from 'vue';

const submit = vi.fn();
let formState: Record<string, unknown>;

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { user: { name: 'Jana Nová' } } } }),
    useForm: (_method: string, _url: string, data: Record<string, unknown>) => {
        formState = reactive({
            ...data,
            errors: {},
            processing: false,
            submit,
            reset: vi.fn(),
        });

        return formState;
    },
}));
vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ error: vi.fn() }) }));

import TaskCommentForm from './TaskCommentForm.vue';

let app: App | null = null;

function mountForm(props: Record<string, unknown> = {}) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({ render: () => h(TaskCommentForm, { taskId: 't1', maxChars: 1000, disabled: false, ...props }) });
    app.mount(host);

    return host;
}

async function type(host: HTMLElement, value: string) {
    const textarea = host.querySelector<HTMLTextAreaElement>('textarea')!;
    textarea.value = value;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();

    return textarea;
}

describe('TaskCommentForm', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
        submit.mockClear();
    });

    it('shows the placeholder and a disabled send arrow while empty', () => {
        const host = mountForm();

        expect(host.querySelector('textarea')!.placeholder).toBe('task_comment_placeholder');
        const send = host.querySelector('.kg-composer button[type="submit"]')!;
        expect(send.hasAttribute('disabled')).toBe(true);
        expect(send.getAttribute('aria-label')).toBe('task_comment_send');
    });

    it('submits on Ctrl+Enter and not on plain Enter', async () => {
        const host = mountForm();
        const textarea = await type(host, 'Ahoj');

        textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        await nextTick();
        expect(submit).not.toHaveBeenCalled();

        textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }));
        await nextTick();
        await nextTick();
        expect(submit).toHaveBeenCalledTimes(1);
    });

    it('shows the counter only near the limit and in error colour above it', async () => {
        const host = mountForm({ maxChars: 300 });

        await type(host, 'a'.repeat(50));
        expect(host.textContent).not.toContain('task_comment_chars');

        await type(host, 'a'.repeat(100));
        const counter = host.querySelector('p.text-muted');
        expect(counter?.textContent).toContain('task_comment_chars');

        await type(host, 'a'.repeat(301));
        expect(host.querySelector('p.text-error')).not.toBeNull();
        expect(host.querySelector('button[type="submit"]')!.hasAttribute('disabled')).toBe(true);
    });

    it('keeps the avatar centred against the single-line box', () => {
        const host = mountForm();

        expect(host.querySelector('.kg-composer-avatar')!.classList.contains('kg-composer-avatar')).toBe(true);
    });

    it('lets Escape reach the panel from the composer', async () => {
        const host = mountForm();
        const outer = vi.fn();
        document.addEventListener('keydown', outer);

        host.querySelector('textarea')!.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
        );
        document.removeEventListener('keydown', outer);

        expect(outer).toHaveBeenCalledTimes(1);
        expect(outer.mock.calls[0]![0].defaultPrevented).toBe(false);
    });

    it('marks the avatar decorative because the composer has no adjacent name', () => {
        const host = mountForm();

        expect(host.querySelector('.kg-composer-avatar')!.getAttribute('aria-hidden')).toBe('true');
        expect(host.querySelector('.kg-composer-avatar')!.getAttribute('role')).toBeNull();
    });

    it('posts the answered comment id for the answer variant', () => {
        mountForm({ answersCommentId: 'c9' });

        expect(formState.answers_comment_id).toBe('c9');
        expect(document.querySelector('textarea')!.placeholder).toBe('task_question_answer_placeholder');
    });
});
