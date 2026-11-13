import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref } from 'vue';
import type { App } from 'vue';

const reload = vi.fn();
const deleteOpen = vi.fn();

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { reload: (...args: unknown[]) => reload(...args), delete: vi.fn() },
    usePage: () => ({ props: { auth: { user: { name: 'Jana Nová' } } } }),
    useForm: (_m: string, _u: string, data: Record<string, unknown>) => ({
        ...data,
        errors: {},
        processing: false,
        submit: vi.fn(),
        reset: vi.fn(),
    }),
}));
vi.mock('@/Components/ConfirmDeleteModal.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Composables/useDeleteConfirm', () => ({
    useDeleteConfirm: () => ({
        state: { isOpen: false },
        openModal: deleteOpen,
        closeModal: vi.fn(),
        confirmDelete: vi.fn(),
        getModalTitle: () => '',
        getModalDescription: () => '',
    }),
}));

import TaskActivitySection from './TaskActivitySection.vue';

let app: App | null = null;
let taskSeq = 0;

function comment(id: string, overrides: Partial<App.Data.Tasks.TaskCommentData> = {}): App.Data.Tasks.TaskCommentData {
    return {
        id,
        author_kind: 'user',
        author_name: 'Peter',
        body: `Text ${id}`,
        created_at: `2026-11-02T0${id.slice(1)}:00:00+00:00`,
        is_mine: true,
        can_delete: true,
        can_edit: true,
        edited_at: null,
        answers_comment_id: null,
        ...overrides,
    };
}

const activity = ref<App.Data.Tasks.TaskActivityFeedData | null | undefined>(undefined);
const comments = ref([comment('c1')]);

const feed: App.Data.Tasks.TaskActivityFeedData = {
    entries: [
        {
            id: 'e1',
            kind: 'updated',
            actor_name: 'Jana',
            occurred_at: '2026-11-03T08:00:00+00:00',
            changes: [{ field: 'status', from: 'open', to: 'done' }],
            attachment_name: null,
        },
    ],
    has_older: true,
};

function mountSection(taskId = `t${++taskSeq}`, extra: Partial<App.Data.Tasks.TaskThreadData> = {}) {
    const task = {
        id: taskId,
        created_by_name: 'Admin',
        created_at: '2026-11-01T08:00:00+00:00',
    } as App.Data.Tasks.TaskDetailData;
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(TaskActivitySection, {
                task,
                thread: {
                    comments: comments.value,
                    question: null,
                    can_comment: true,
                    max_body_chars: 1000,
                    ...extra,
                } as unknown as App.Data.Tasks.TaskThreadData,
                attachments: { attachments: [] } as unknown as App.Data.Tasks.TaskAttachmentListData,
                activity: activity.value,
                uploading: false,
            }),
    });
    app.mount(host);

    return host;
}

function click(host: HTMLElement, text: string) {
    Array.from(host.querySelectorAll('button'))
        .find((b) => b.textContent?.includes(text))!
        .click();

    return nextTick();
}

function rows(host: HTMLElement) {
    return Array.from(host.querySelectorAll('ul > li')).map((li) => li.textContent ?? '');
}

async function openMenu(host: HTMLElement, id: string) {
    host.querySelector<HTMLElement>(`#task-comment-${id} [aria-haspopup="menu"]`)!.click();
    await nextTick();
    await nextTick();
}

function menuItem(text: string) {
    return Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find((i) =>
        i.textContent?.includes(text),
    );
}

describe('TaskActivitySection', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
        reload.mockReset();
        deleteOpen.mockReset();
        window.localStorage.clear();
        window.sessionStorage.clear();
        activity.value = undefined;
        comments.value = [comment('c1')];
    });

    it('shows the created row and the comment on the comments tab, then the composer last', () => {
        const host = mountSection();
        const list = rows(host);

        expect(list[0]).toContain('task_feed_created');
        expect(list[1]).toContain('Text c1');
        expect(host.querySelector('form')).not.toBeNull();
        expect(host.querySelector('#task-comment-c1')).not.toBeNull();
    });

    it('keeps the header on one row: nowrap tabs and an icon-only sort toggle below sm that keeps its name', () => {
        const host = mountSection();
        const sort = host.querySelector('button[aria-label="task_feed_oldest"]')!;

        expect(sort.hasAttribute('aria-pressed')).toBe(false);
        expect(sort.querySelector('span')!.className).toContain('hidden');
        expect(sort.querySelector('span')!.className).toContain('sm:inline');
        expect(host.querySelector('.tabs-box .tab')!.classList.contains('whitespace-nowrap')).toBe(true);
    });

    it('flips the order with the sort toggle and remembers it', async () => {
        const host = mountSection();

        await click(host, 'task_feed_oldest');

        expect(rows(host)[0]).toContain('Text c1');
        expect(window.localStorage.getItem('kg.taskFeedSort')).toBe('newest');
    });

    it('requests the activity on the first open of the all tab and keeps the tab when the prop arrives', async () => {
        const host = mountSection();

        await click(host, 'task_feed_all');
        expect(reload).toHaveBeenCalledTimes(1);

        activity.value = feed;
        await nextTick();
        (reload.mock.calls[0]![0] as { onFinish: () => void }).onFinish();
        await nextTick();
        await nextTick();

        expect(
            host.querySelector('[aria-label="task_feed_view"] button:nth-child(2)')?.getAttribute('aria-pressed'),
        ).toBe('true');
        expect(host.textContent).toContain('task_feed_older_hidden');
        expect(host.textContent).toContain('task_feed_change_from_to');
        expect(host.textContent).not.toContain('task_feed_created');
    });

    it('shows the loading row while the first load is pending', async () => {
        const host = mountSection();

        await click(host, 'task_feed_all');

        expect(host.querySelector('[role="status"]')?.textContent).toContain('loading');
        expect(host.querySelector('ul')).toBeNull();
    });

    it('renders created and updated rows when the success callback fires before the prop arrives', async () => {
        const host = mountSection();

        await click(host, 'task_feed_all');
        const options = reload.mock.calls[0]![0] as { onSuccess: () => void; onFinish: () => void };
        options.onSuccess();
        options.onFinish();
        await nextTick();
        await nextTick();

        activity.value = {
            has_older: false,
            entries: [
                {
                    id: 'created',
                    kind: 'created',
                    actor_name: 'Admin',
                    occurred_at: '2026-11-01T08:00:00+00:00',
                    changes: [],
                    attachment_name: null,
                },
                ...feed.entries,
            ],
        };
        await nextTick();

        expect(host.querySelector('[role="status"]')).toBeNull();
        expect(rows(host).some((r) => r.includes('task_feed_created'))).toBe(true);
        expect(rows(host).some((r) => r.includes('task_feed_completed'))).toBe(true);
    });

    it('keeps the all tab and the entries after the section remounts for the same task', async () => {
        const first = mountSection('same');
        await click(first, 'task_feed_all');
        activity.value = feed;
        await nextTick();
        app?.unmount();
        document.body.innerHTML = '';

        const second = mountSection('same');
        await nextTick();

        expect(
            second.querySelector('[aria-label="task_feed_view"] button:nth-child(2)')?.getAttribute('aria-pressed'),
        ).toBe('true');
        expect(second.textContent).toContain('task_feed_change_from_to');
    });

    it('shows the failure row with retry when the first load fails', async () => {
        const host = mountSection();

        await click(host, 'task_feed_all');
        const options = reload.mock.calls[0]![0] as { onHttpException: () => void; onFinish: () => void };
        options.onHttpException();
        options.onFinish();
        await nextTick();
        await nextTick();

        expect(host.querySelector('[role="alert"]')?.textContent).toContain('task_feed_load_failed');
        await click(host, 'retry');
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('edits one comment at a time and restores the body on cancel', async () => {
        comments.value = [comment('c1'), comment('c2')];
        const host = mountSection();

        await openMenu(host, 'c1');
        menuItem('edit')!.click();
        await nextTick();
        await nextTick();

        expect(host.querySelector('#task-comment-c1 textarea')).not.toBeNull();
        expect(host.querySelector('#task-comment-c1')?.textContent).not.toContain('Text c1');

        await openMenu(host, 'c2');
        expect(menuItem('edit')).toBeUndefined();
        expect(menuItem('delete')).toBeDefined();

        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await nextTick();
        await click(host.querySelector<HTMLElement>('#task-comment-c1')!, 'cancel');
        await nextTick();

        expect(host.querySelector('#task-comment-c1 textarea')).toBeNull();
        expect(host.querySelector('#task-comment-c1')?.textContent).toContain('Text c1');
    });

    it('still opens the delete confirm from the menu', async () => {
        const host = mountSection();

        await openMenu(host, 'c1');
        menuItem('delete')!.click();
        await nextTick();

        expect(deleteOpen).toHaveBeenCalledTimes(1);
    });

    it('renders the AI answer form under the question comment', () => {
        comments.value = [comment('c1', { author_kind: 'ai', can_edit: false, can_delete: false, is_mine: false })];
        const host = mountSection(undefined, {
            question: { comment_id: 'c1', answered: false, can_answer: true },
        } as unknown as Partial<App.Data.Tasks.TaskThreadData>);

        expect(host.querySelectorAll('#task-comment-c1 form')).toHaveLength(1);
    });
});
