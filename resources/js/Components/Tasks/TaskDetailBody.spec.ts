import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App as VueApp } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { patch: vi.fn(), post: vi.fn(), delete: vi.fn(), get: vi.fn(), reload: vi.fn() },
    Link: { render: () => null },
    usePage: () => ({ props: { auth: { user: { name: 'Admin' } } } }),
    useForm: (_method: string, _url: string, data: Record<string, unknown>) => ({
        ...data,
        errors: {},
        processing: false,
        transform: () => ({}),
        submit: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    }),
}));
vi.mock('@/Components/ConfirmDeleteModal.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Composables/useTaskAttachmentUpload', async () => {
    const { ref } = await import('vue');

    return {
        useTaskAttachmentUpload: () => ({ items: ref([]), uploading: ref(false), add: vi.fn(), dismiss: vi.fn() }),
    };
});
vi.mock('@/Composables/useTaskAiRun', async () => {
    const { ref } = await import('vue');

    return {
        useTaskAiRun: () => ({
            starting: ref(false),
            errors: ref({}),
            statusCheckFailed: ref(false),
            start: vi.fn(),
        }),
    };
});

import TaskDetailBody from './TaskDetailBody.vue';

let app: VueApp | null = null;

function makeTask(can: { update: boolean }, overrides: Record<string, unknown> = {}) {
    return {
        id: 't1',
        tenant_id: null,
        tenant_name: null,
        title: 'Vymeniť žiarovky',
        status: 'open',
        priority: 'normal',
        due_at: null,
        assignee_name: null,
        subject: null,
        is_global: true,
        created_at: '2026-11-01T08:00:00+00:00',
        description: null,
        remind_at: null,
        remind_on: null,
        remind_slot: null,
        completed_at: null,
        source: 'manual',
        assigned_membership_id: null,
        created_by_name: 'Admin',
        can: { delete: false, assign: false, ...can },
        attachments_count: 0,
        is_active: true,
        ...overrides,
    };
}

const thread = {
    comments: [],
    latest_run: null,
    question: null,
    pending_actions: [],
    ai_available: false,
    can_comment: true,
    can_start_run: false,
    run_companies: [],
    max_body_chars: 5000,
};

function mountBody(can: { update: boolean }, taskOverrides: Record<string, unknown> = {}, threadOverrides = {}) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const props = {
        task: makeTask(can, taskOverrides),
        attachments: {
            attachments: [],
            can_add: true,
            constraints: {
                max_size_kb: 10240,
                max_count: 20,
                allowed_mimes: ['application/pdf'],
                allowed_extensions: ['pdf'],
            },
        },
        activity: undefined,
        context: {
            companies: [],
            members: {},
            reminder_slots: [],
            default_remind_on: '2026-11-14',
            default_remind_slot: 'morning',
        },
        thread: { ...thread, ...threadOverrides },
    };
    app = createApp({ render: () => h(TaskDetailBody, props as never) });
    app.mount(host);

    return host;
}

describe('TaskDetailBody', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('is an inline editor when the user can update', async () => {
        const host = mountBody({ update: true });
        await nextTick();

        const title = host.querySelector<HTMLTextAreaElement>('textarea.kg-heading-input');
        expect(title).not.toBeNull();
        expect(title!.value).toBe('Vymeniť žiarovky');

        const due = host.querySelector<HTMLElement>('button[aria-haspopup="dialog"]');
        expect(due).not.toBeNull();
        expect(due!.textContent).toContain('task_no_due_date');

        expect(host.querySelector('button[aria-haspopup="menu"]')).not.toBeNull();
        expect(host.textContent).not.toContain('task_edit');
        expect(host.querySelector('.card')).toBeNull();
    });

    it('opens an own calendar for the due date, not a native datetime input', async () => {
        const host = mountBody({ update: true });
        await nextTick();

        const due = host.querySelector<HTMLElement>('button[aria-label^="task_due_at"]');
        due!.click();
        await nextTick();
        await nextTick();

        expect(document.body.querySelector('[role="grid"]')).not.toBeNull();
        expect(document.body.querySelector('input[type="datetime-local"]')).toBeNull();
    });

    it('renders plain text without controls when the user cannot update', async () => {
        const host = mountBody({ update: false });
        await nextTick();

        expect(host.querySelector('textarea.kg-heading-input')).toBeNull();
        expect(host.querySelector('button[aria-haspopup]')).toBeNull();
        expect(host.querySelector('.card')).toBeNull();

        const heading = host.querySelector('h2.kg-heading-input');
        expect(heading).not.toBeNull();
        expect(heading!.textContent).toBe('Vymeniť žiarovky');
        expect(host.textContent).not.toContain('task_no_due_date');
    });

    it('hides the AI section when AI is not configured and nothing ran', async () => {
        const host = mountBody({ update: true }, {}, { can_start_run: true });
        await nextTick();

        expect(host.textContent).not.toContain('task_ai_title');
    });

    it('keeps the AI section when a past run exists even without AI configured', async () => {
        const host = mountBody(
            { update: true },
            {},
            {
                latest_run: {
                    id: 'r1',
                    status: 'done',
                    is_active: false,
                    closed_task: false,
                    started_by_name: 'Admin',
                    tenant_id: null,
                    tenant_name: null,
                    actions: [],
                },
            },
        );
        await nextTick();

        expect(host.textContent).toContain('task_ai_title');
    });

    it('has no primary button in the attachments header', async () => {
        const host = mountBody({ update: true });
        await nextTick();

        const add = Array.from(host.querySelectorAll('button')).find((b) =>
            b.textContent?.includes('task_attachment_add_short'),
        );

        expect(add).toBeDefined();
        expect(add!.classList.contains('btn-primary')).toBe(false);
        expect(add!.classList.contains('btn-ghost')).toBe(true);
        expect(host.textContent).not.toContain('task_attachments_empty');
    });

    it('shows the feed view switch and the composer placeholder', async () => {
        const host = mountBody({ update: true });
        await nextTick();

        const switchGroup = host.querySelector('[role="group"][aria-label="task_feed_view"]');
        expect(switchGroup).not.toBeNull();
        expect(switchGroup!.querySelectorAll('button')).toHaveLength(2);

        const composer = host.querySelector<HTMLTextAreaElement>('textarea[placeholder="task_comment_placeholder"]');
        expect(composer).not.toBeNull();
    });

    it('hides an empty description section for a read-only user', async () => {
        const host = mountBody({ update: false });
        await nextTick();

        expect(host.querySelector('.kg-quiet-textarea')).toBeNull();
        expect(host.textContent).not.toContain('description');
    });
});
