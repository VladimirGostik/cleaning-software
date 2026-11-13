import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));

import TaskReminderField from './TaskReminderField.vue';

let app: App | null = null;

const context = {
    reminder_slots: [
        { slot: 'morning', hour: 7 },
        { slot: 'noon', hour: 12 },
    ],
    default_remind_on: '2026-11-20',
    default_remind_slot: 'morning',
} as unknown as App.Data.Tasks.TaskFormContextData;

function mountField(taskOverrides: Record<string, unknown> = {}, editable = true) {
    const save = vi.fn();
    const task = { remind_on: null, remind_slot: null, ...taskOverrides } as unknown as App.Data.Tasks.TaskDetailData;
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () => h(TaskReminderField, { task, context, editable, disabled: false, onSave: save }),
    });
    app.mount(host);

    return { host, save };
}

async function openPopover(host: HTMLElement) {
    host.querySelector<HTMLElement>('button[aria-haspopup="dialog"]')!.click();
    await nextTick();
    await nextTick();
}

describe('TaskReminderField', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('shows the placeholder in the trigger text and the label when there is no reminder', () => {
        const { host } = mountField();
        const trigger = host.querySelector('button')!;

        expect(trigger.textContent).toContain('task_no_reminder');
        expect(trigger.getAttribute('aria-label')).toBe('task_remind_at: task_no_reminder');
    });

    async function closeByPointer() {
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await nextTick();
        await nextTick();
    }

    function toggleTo(checked: boolean) {
        const toggle = document.body.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
        toggle.checked = checked;
        toggle.dispatchEvent(new Event('change', { bubbles: true }));
    }

    it('saves the default pair once when closed after the toggle is switched on', async () => {
        const { host, save } = mountField();
        await openPopover(host);

        toggleTo(true);
        await nextTick();
        expect(save).not.toHaveBeenCalled();

        await closeByPointer();

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith({ remind_on: '2026-11-20', remind_slot: 'morning' });
    });

    it('clears both keys when closed after the toggle is switched off', async () => {
        const { host, save } = mountField({ remind_on: '2026-11-10', remind_slot: 'noon' });
        await openPopover(host);

        toggleTo(false);
        await nextTick();
        await closeByPointer();

        expect(save).toHaveBeenCalledWith({ remind_on: null, remind_slot: null });
    });

    it('sends a calendar day and a slot together as one pair on close', async () => {
        const { host, save } = mountField({ remind_on: '2026-11-10', remind_slot: 'noon' });
        await openPopover(host);

        document.body.querySelector<HTMLElement>('[data-day="2026-11-12"]')!.click();
        document.body
            .querySelectorAll<HTMLInputElement>('input[type="radio"]')[0]!
            .dispatchEvent(new Event('change', { bubbles: true }));
        await nextTick();
        expect(save).not.toHaveBeenCalled();

        await closeByPointer();

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith({ remind_on: '2026-11-12', remind_slot: 'morning' });
    });

    it('discards the draft on Escape', async () => {
        const { host, save } = mountField({ remind_on: '2026-11-10', remind_slot: 'noon' });
        await openPopover(host);

        document.body.querySelector<HTMLElement>('[data-day="2026-11-12"]')!.click();
        await nextTick();
        document.body
            .querySelector('[data-day="2026-11-12"]')!
            .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();
        await nextTick();

        expect(save).not.toHaveBeenCalled();
    });

    it('does not save when closed unchanged', async () => {
        const { host, save } = mountField({ remind_on: '2026-11-10', remind_slot: 'noon' });
        await openPopover(host);

        await closeByPointer();

        expect(save).not.toHaveBeenCalled();
    });

    it('renders plain text without a trigger when not editable', () => {
        const { host } = mountField({}, false);

        expect(host.querySelector('button')).toBeNull();
    });
});
