import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
    Link: { render: () => null },
    useForm: (_method: string, _url: string, data: Record<string, unknown>) => ({
        ...data,
        errors: {},
        processing: false,
        submit: vi.fn(),
        defaults: vi.fn(),
    }),
}));

import TaskPropertiesList from './TaskPropertiesList.vue';

let app: App | null = null;

const context = {
    members: {},
    reminder_slots: [{ slot: 'morning', hour: 7 }],
    default_remind_on: '2026-11-20',
    default_remind_slot: 'morning',
} as unknown as App.Data.Tasks.TaskFormContextData;

function mountList(taskOverrides: Record<string, unknown> = {}, editable = true) {
    const save = vi.fn();
    const task = {
        id: 't1',
        tenant_id: null,
        tenant_name: null,
        status: 'open',
        priority: 'normal',
        due_at: null,
        remind_on: null,
        remind_slot: null,
        assignee_name: null,
        assigned_membership_id: null,
        subject: null,
        is_global: true,
        can: { update: true, assign: false },
        ...taskOverrides,
    } as unknown as App.Data.Tasks.TaskDetailData;
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () => h(TaskPropertiesList, { task, context, editable, disabled: false, errors: {}, onSave: save }),
    });
    app.mount(host);

    return { host, save };
}

async function openDue(host: HTMLElement) {
    host.querySelector<HTMLElement>('button[aria-label^="task_due_at"]')!.click();
    await nextTick();
    await nextTick();
}

describe('TaskPropertiesList', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('shows the muted due-date placeholder on the trigger', () => {
        const { host } = mountList();
        const trigger = host.querySelector('button[aria-label^="task_due_at"]')!;

        expect(trigger.textContent).toContain('task_no_due_date');
        expect(trigger.querySelector('.kg-placeholder')).not.toBeNull();
    });

    it('saves one changed due date when the popover closes', async () => {
        const { host, save } = mountList();
        await openDue(host);

        document.body.querySelector<HTMLElement>('[data-day="2026-11-20"]')!.click();
        await nextTick();
        expect(save).not.toHaveBeenCalled();

        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await nextTick();

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith({ due_at: new Date('2026-11-20T12:00').toISOString() });
    });

    it('discards the draft on Escape', async () => {
        const { host, save } = mountList();
        await openDue(host);

        document.body.querySelector<HTMLElement>('[data-day="2026-11-20"]')!.click();
        await nextTick();
        document.body
            .querySelector('[data-day="2026-11-20"]')!
            .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();
        await nextTick();

        expect(save).not.toHaveBeenCalled();
        expect(document.body.querySelector('[role="grid"]')).toBeNull();
    });

    it('does not save when the popover closes unchanged', async () => {
        const { host, save } = mountList();
        await openDue(host);

        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await nextTick();

        expect(save).not.toHaveBeenCalled();
    });

    it('saves a quick pick at once and closes', async () => {
        const { host, save } = mountList();
        await openDue(host);

        const today = Array.from(document.body.querySelectorAll('button')).find((b) =>
            b.textContent?.includes('date_picker_today'),
        )!;
        today.click();
        await nextTick();
        await nextTick();

        expect(save).toHaveBeenCalledTimes(1);
        expect(document.body.querySelector('[role="grid"]')).toBeNull();
    });

    it('clears the due date at once with null', async () => {
        const { host, save } = mountList({ due_at: '2026-11-20T08:00:00+00:00' });
        await openDue(host);

        const clear = Array.from(document.body.querySelectorAll('button')).find((b) =>
            b.textContent?.includes('date_picker_clear'),
        )!;
        clear.click();
        await nextTick();
        await nextTick();

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith({ due_at: null });
    });

    it('renders no empty read-only rows and no triggers when not editable', () => {
        const { host } = mountList({ can: { update: false, assign: false } }, false);

        expect(host.querySelector('button[aria-haspopup="dialog"]')).toBeNull();
        expect(host.textContent).not.toContain('task_no_due_date');
        expect(host.textContent).not.toContain('task_due_at');
        expect(host.textContent).not.toContain('task_remind_at');
        expect(host.textContent).not.toContain('task_assignee');
    });

    it('renders no linked-record row without a record', () => {
        const { host } = mountList();

        expect(host.textContent).not.toContain('task_subject');
    });

    it('keeps editable empty rows with their placeholders', () => {
        const { host } = mountList();

        expect(host.textContent).toContain('task_due_at');
        expect(host.textContent).toContain('task_remind_at');
    });
});
