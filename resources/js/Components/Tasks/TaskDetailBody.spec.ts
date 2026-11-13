import { afterEach, describe, expect, it, vi } from 'vitest';
import { computed, createApp, effectScope, h, nextTick, reactive, ref } from 'vue';
import type { App as VueApp } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: {
        patch: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        get: vi.fn(),
        reload: vi.fn(),
        visit: vi.fn(),
        replace: vi.fn(),
    },
    Link: { render: () => null },
    usePage: () => ({ url: '/tasks?view=board', props: { auth: { user: { name: 'Admin' } } } }),
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

import { router } from '@inertiajs/vue3';
import { useTaskCreate } from '@/Composables/useTaskCreate';
import { useTaskPanel } from '@/Composables/useTaskPanel';
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

    describe('create session', () => {
        const attachments = {
            attachments: [],
            can_add: true,
            constraints: {
                max_size_kb: 10240,
                max_count: 20,
                allowed_mimes: ['application/pdf'],
                allowed_extensions: ['pdf'],
            },
        };
        const context = {
            companies: [],
            members: {},
            reminder_slots: [],
            default_remind_on: '2026-11-14',
            default_remind_slot: 'morning',
        };

        function makeCreator() {
            const state = ref('drafting');
            const createdId = ref<string | null>(null);
            const draft = reactive({ title: '', tenantId: null as string | null, status: null as string | null });
            let handler: ((title: string | null) => void) | null = null;

            return {
                state,
                sessionKey: ref(1),
                createdId,
                draft,
                errors: ref({}),
                companyOptions: computed(() => [
                    { value: null, label: 'Globálna' },
                    { value: 'tA', label: 'Alfa' },
                ]),
                firmLocked: computed(() => state.value === 'creating' || state.value === 'created'),
                autoCreate: computed(() => true),
                open: vi.fn(),
                type: vi.fn((title: string) => {
                    draft.title = title;
                }),
                holdPause: vi.fn(),
                releasePause: vi.fn(),
                setCompany: vi.fn((id: string | null) => {
                    draft.tenantId = id;
                }),
                createNow: vi.fn(),
                leave: vi.fn(),
                discard: vi.fn(),
                onCreated: vi.fn((next: (title: string | null) => void) => {
                    handler = next;

                    return () => undefined;
                }),
                fire: (title: string | null) => handler?.(title),
            };
        }

        function mountSession() {
            const creator = makeCreator();
            const host = document.createElement('div');
            document.body.appendChild(host);
            const shown = reactive({ task: null as ReturnType<typeof makeTask> | null });
            app = createApp({
                render: () =>
                    h(TaskDetailBody, {
                        task: shown.task,
                        attachments: shown.task ? attachments : null,
                        thread: shown.task ? thread : null,
                        context,
                        creator,
                    } as never),
            });
            app.mount(host);

            return {
                host,
                creator,
                arrive: async (can: { update: boolean } = { update: true }) => {
                    creator.createdId.value = 'n1';
                    creator.state.value = 'created';
                    shown.task = makeTask(can, { id: 'n1', title: 'Zav', is_global: true });
                    await nextTick();
                    await nextTick();
                },
            };
        }

        function titleOf(host: HTMLElement): HTMLTextAreaElement {
            return host.querySelector<HTMLTextAreaElement>('textarea.kg-heading-input')!;
        }

        function typeInto(textarea: HTMLTextAreaElement, value: string) {
            textarea.focus();
            textarea.value = value;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function enter(textarea: HTMLTextAreaElement) {
            textarea.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
        }

        it('renders the firm choice above the focused title, the hint and discard, and no properties', async () => {
            const { host } = mountSession();
            await nextTick();

            const firm = host.querySelector('button[aria-haspopup="menu"]')!;
            const title = titleOf(host);

            expect(firm.compareDocumentPosition(title) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
            expect(host.textContent).toContain('task_new_hint');
            expect(
                Array.from(host.querySelectorAll('button')).some((b) => b.textContent?.includes('task_discard')),
            ).toBe(true);
            expect(host.querySelector('button[aria-label^="task_due_at"]')).toBeNull();
            expect(document.activeElement).toBe(title);
        });

        it('routes typing to the creator and Enter to createNow without a line break', async () => {
            const { host, creator } = mountSession();
            await nextTick();

            const title = titleOf(host);
            typeInto(title, 'Zav');
            enter(title);
            await nextTick();

            expect(creator.type).toHaveBeenCalledWith('Zav');
            expect(creator.createNow).toHaveBeenCalledTimes(1);
            expect(title.value).not.toContain('\n');
        });

        it('keeps the same focused title element when the task arrives and locks the firm', async () => {
            const { host, arrive } = mountSession();
            await nextTick();

            const title = titleOf(host);
            typeInto(title, 'Zav');
            await arrive();

            expect(titleOf(host)).toBe(title);
            expect(document.activeElement).toBe(title);
            expect(host.querySelector('.sr-only')?.textContent).toBe('task_company_locked');
            expect(host.querySelector('[role="img"]')).toBeNull();
            expect(host.querySelector('button[aria-label^="task_due_at"]')).not.toBeNull();
        });

        it('sends the title typed during the request as one patch once the task has arrived', async () => {
            const { host, creator, arrive } = mountSession();
            await nextTick();
            vi.mocked(router.patch).mockClear();

            typeInto(titleOf(host), 'Zavolať');
            creator.createdId.value = 'n1';
            creator.fire('Zavolať');
            await arrive();

            expect(router.patch).toHaveBeenCalledTimes(1);
            expect(router.patch).toHaveBeenCalledWith('/tasks/n1', { title: 'Zavolať' }, expect.anything());
        });

        it('focuses the description after an Enter create', async () => {
            const { host, arrive } = mountSession();
            await nextTick();

            const title = titleOf(host);
            typeInto(title, 'Zav');
            enter(title);
            await arrive();
            await nextTick();

            const description = host.querySelector('textarea[placeholder="task_add_description"]');

            expect(description).not.toBeNull();
            expect(document.activeElement).toBe(description);
        });

        it('shows a static heading and sends no pending title when the creator cannot update', async () => {
            const { host, creator, arrive } = mountSession();
            await nextTick();
            vi.mocked(router.patch).mockClear();

            creator.createdId.value = 'n1';
            creator.fire('Zavolať');
            await arrive({ update: false });

            expect(host.querySelector('textarea.kg-heading-input')).toBeNull();
            expect(host.querySelector('h2.kg-heading-input')).not.toBeNull();
            expect(host.textContent).toContain('task_created_read_only');
            expect(router.patch).not.toHaveBeenCalled();
        });

        it('shows a placeholder on the empty title and describes it with the hint', async () => {
            const { host } = mountSession();
            await nextTick();

            const title = titleOf(host);
            const hint = Array.from(host.querySelectorAll('p')).find((p) => p.textContent === 'task_new_hint')!;

            expect(title.getAttribute('placeholder')).toBe('task_title');
            expect(title.getAttribute('aria-describedby')).toBe(hint.id);
            expect(hint.id).not.toBe('');
        });

        it('keeps characters typed in the create window when the title is blurred there', async () => {
            const { host, creator, arrive } = mountSession();
            await nextTick();

            const title = titleOf(host);
            typeInto(title, 'Zav');
            typeInto(title, 'Zavolať');
            title.blur();
            await nextTick();

            creator.createdId.value = 'n1';
            creator.fire('Zavolať');
            await arrive();
            await nextTick();

            expect(titleOf(host).value).toBe('Zavolať');
        });

        it('sends the pending title to the new task before the visit to another task', async () => {
            const scope = effectScope();
            const selected = ref<string | null>(null);
            const creator = scope.run(() =>
                useTaskCreate({
                    context: () => context as never,
                    activeTenantId: () => null,
                    selectedTaskId: () => selected.value,
                    mayEditInActive: () => true,
                }),
            )!;
            creator.open();

            const host = document.createElement('div');
            document.body.appendChild(host);
            const shown = reactive({ task: null as ReturnType<typeof makeTask> | null });
            app = createApp({
                render: () =>
                    h(TaskDetailBody, {
                        task: shown.task,
                        attachments: shown.task ? attachments : null,
                        thread: shown.task ? thread : null,
                        context,
                        creator,
                    } as never),
            });
            app.mount(host);
            await nextTick();

            vi.mocked(router.post).mockReset();
            vi.mocked(router.post).mockImplementation(((_u: string, _b: unknown, options: { onStart: () => void }) =>
                options.onStart()) as never);
            vi.mocked(router.patch).mockReset();
            vi.mocked(router.patch).mockImplementation(((
                _u: string,
                _d: unknown,
                patchOptions: { onStart: () => void },
            ) => patchOptions.onStart()) as never);
            vi.mocked(router.visit).mockClear();

            const title = titleOf(host);
            typeInto(title, 'Zav');
            creator.createNow();
            typeInto(title, 'Zavolať');

            const opening = useTaskPanel().open('taskB');
            await nextTick();

            const options = vi.mocked(router.post).mock.calls[0][2] as unknown as {
                onSuccess: (page: unknown) => void;
                onFinish: () => void;
            };
            shown.task = makeTask({ update: true }, { id: 'n1', title: 'Zav', is_global: true });
            options.onSuccess({ url: '/tasks?view=board&task=n1', props: { selected_task: { id: 'n1' } } });
            options.onFinish();
            await vi.waitFor(() => expect(router.patch).toHaveBeenCalledTimes(1));
            const patchOptions = vi.mocked(router.patch).mock.calls[0][2] as unknown as {
                onSuccess: () => void;
                onFinish: () => void;
            };
            expect(router.visit).not.toHaveBeenCalled();
            patchOptions.onSuccess();
            patchOptions.onFinish();
            await opening;

            expect(router.patch).toHaveBeenCalledWith('/tasks/n1', { title: 'Zavolať' }, expect.anything());
            expect(router.visit).toHaveBeenCalledTimes(1);
            expect(vi.mocked(router.patch).mock.invocationCallOrder[0]).toBeLessThan(
                vi.mocked(router.visit).mock.invocationCallOrder[0],
            );
        });
    });
});
