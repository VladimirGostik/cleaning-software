import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, string>) =>
            params ? `${key}:${Object.values(params).join('|')}` : key,
        locale: { value: 'sk' },
    }),
}));

import TaskCommentItem from './TaskCommentItem.vue';

let app: App | null = null;

function comment(overrides: Partial<App.Data.Tasks.TaskCommentData> = {}): App.Data.Tasks.TaskCommentData {
    return {
        id: 'c1',
        author_kind: 'user',
        author_name: 'Peter Kováč',
        body: 'Hotovo zajtra',
        created_at: '2026-11-02T08:00:00+00:00',
        is_mine: false,
        can_delete: true,
        can_edit: true,
        edited_at: null,
        answers_comment_id: null,
        ...overrides,
    };
}

function mountItem(data: App.Data.Tasks.TaskCommentData, props: Record<string, unknown> = {}) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(TaskCommentItem, {
                comment: data,
                questionState: null,
                uploading: false,
                editing: false,
                canStartEdit: true,
                ...props,
            }),
    });
    app.mount(host);

    return host;
}

describe('TaskCommentItem', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('hides the delete button behind a menu trigger', async () => {
        const host = mountItem(comment());

        expect(host.querySelector('button[aria-label="task_comment_delete"]')).toBeNull();
        const trigger = host.querySelector<HTMLElement>('button[aria-haspopup="menu"]');
        expect(trigger).not.toBeNull();

        trigger!.click();
        await nextTick();
        await nextTick();

        const items = Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((i) => i.textContent?.trim());
        expect(items).toEqual(['edit', 'delete']);
    });

    it('marks the own comment with the you hint', () => {
        const host = mountItem(comment({ is_mine: true }));

        expect(host.textContent).toContain('task_comment_you');
        expect(mountOther().textContent).not.toContain('task_comment_you');
    });

    it('shows the AI name, the starter and no edit for an AI comment', async () => {
        const host = mountItem(comment({ author_kind: 'ai', author_name: 'Jana', can_edit: false, can_delete: false }));

        expect(host.textContent).toContain('task_comment_ai_name');
        expect(host.textContent).toContain('task_comment_ai_on_behalf:Jana');
        expect(host.textContent).not.toContain('edit');
        expect(host.querySelector('button[aria-haspopup="menu"]')).toBeNull();
    });

    it('shows the edited mark with the edit time in the title', () => {
        const host = mountItem(comment({ edited_at: '2026-11-03T09:30:00+00:00' }));
        const mark = Array.from(host.querySelectorAll('[title]')).find((el) =>
            el.textContent?.includes('task_comment_edited'),
        );

        expect(mark).toBeDefined();
        expect(mark!.getAttribute('title')).toContain('task_comment_edited_at');
        expect(host.textContent).not.toContain('task_comment_edited_at');
    });

    it('does not show the edited mark for a never edited comment', () => {
        expect(mountItem(comment()).textContent).not.toContain('task_comment_edited');
    });

    it('renders an avatar with the author name', () => {
        const host = mountItem(comment());

        const avatar = host.querySelector('[aria-hidden="true"]');

        expect(avatar?.textContent?.trim()).toBe('PK');
        expect(avatar?.getAttribute('role')).toBeNull();
        expect(host.textContent).toContain('Peter Kováč');
    });

    it('drops the edit item when another comment is being edited', async () => {
        const host = mountItem(comment(), { canStartEdit: false });

        host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
        await nextTick();
        await nextTick();

        const items = Array.from(document.body.querySelectorAll('[role="menuitem"]')).map((i) => i.textContent?.trim());
        expect(items).toEqual(['delete']);
    });
});

function mountOther() {
    app?.unmount();

    return mountItem(comment({ is_mine: false }));
}
