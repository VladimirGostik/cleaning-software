import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import TaskCommentMenu from './TaskCommentMenu.vue';

let app: App | null = null;

function mountMenu(props: { canEdit: boolean; canDelete: boolean }) {
    const onEdit = vi.fn();
    const onDelete = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({ render: () => h(TaskCommentMenu, { ...props, onEdit, onDelete }) });
    app.mount(host);

    return { host, onEdit, onDelete };
}

async function open(host: HTMLElement) {
    host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
    await nextTick();
    await nextTick();
}

function items() {
    return Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]'));
}

describe('TaskCommentMenu', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('renders nothing without any right', () => {
        const { host } = mountMenu({ canEdit: false, canDelete: false });

        expect(host.querySelector('button')).toBeNull();
    });

    it('lists the items by right', async () => {
        const both = mountMenu({ canEdit: true, canDelete: true });
        await open(both.host);
        expect(items().map((i) => i.textContent?.trim())).toEqual(['edit', 'delete']);
    });

    it('lists only delete when edit is not allowed', async () => {
        const { host } = mountMenu({ canEdit: false, canDelete: true });
        await open(host);

        expect(items().map((i) => i.textContent?.trim())).toEqual(['delete']);
    });

    it('moves with the arrow keys, Home and End', async () => {
        const { host } = mountMenu({ canEdit: true, canDelete: true });
        await open(host);
        const [edit, del] = items();

        expect(document.activeElement).toBe(edit);
        edit!.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
        expect(document.activeElement).toBe(del);
        del!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));
        expect(document.activeElement).toBe(edit);
        edit!.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));
        expect(document.activeElement).toBe(del);
    });

    it('closes on Escape and returns focus to the trigger', async () => {
        const { host } = mountMenu({ canEdit: true, canDelete: true });
        await open(host);

        items()[0]!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();
        await nextTick();

        expect(items()).toHaveLength(0);
        expect(document.activeElement).toBe(host.querySelector('button[aria-haspopup="menu"]'));
    });

    it('emits edit and delete and closes', async () => {
        const { host, onEdit, onDelete } = mountMenu({ canEdit: true, canDelete: true });

        await open(host);
        items()[0]!.click();
        await nextTick();
        expect(onEdit).toHaveBeenCalledTimes(1);
        expect(items()).toHaveLength(0);

        await open(host);
        items()[1]!.click();
        expect(onDelete).toHaveBeenCalledTimes(1);
    });
});
