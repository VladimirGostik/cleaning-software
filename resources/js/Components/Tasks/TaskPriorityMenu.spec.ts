import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import TaskPriorityMenu from './TaskPriorityMenu.vue';

let app: App | null = null;

function items(): HTMLElement[] {
    return Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitemradio"]'));
}

function mountMenu(props: Record<string, unknown> = {}) {
    const select = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({ render: () => h(TaskPriorityMenu, { priority: 'normal', onSelect: select, ...props }) });
    app.mount(host);

    return { host, select };
}

async function open(host: HTMLElement) {
    host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
    await nextTick();
    await nextTick();
}

describe('TaskPriorityMenu', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('opens from the chip and lists every priority with the current one checked', async () => {
        const { host } = mountMenu();
        await open(host);

        expect(host.querySelector('button[aria-haspopup="menu"]')!.getAttribute('aria-expanded')).toBe('true');
        expect(items().map((el) => el.getAttribute('aria-checked'))).toEqual(['false', 'true', 'false']);
    });

    it('moves focus with Arrow, Home and End', async () => {
        const { host } = mountMenu();
        await open(host);
        const list = items();
        const menu = document.body.querySelector<HTMLElement>('[role="menu"]')!;
        const press = (key: string) => menu.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));

        list[1]!.focus();
        press('ArrowDown');
        expect(document.activeElement).toBe(list[2]);

        press('ArrowDown');
        expect(document.activeElement).toBe(list[0]);

        press('End');
        expect(document.activeElement).toBe(list[2]);

        press('Home');
        expect(document.activeElement).toBe(list[0]);
    });

    it('emits select for another priority and closes', async () => {
        const { host, select } = mountMenu();
        await open(host);

        items()[2]!.click();
        await nextTick();

        expect(select).toHaveBeenCalledWith('high');
        expect(items()).toHaveLength(0);
    });

    it('does not emit when the current priority is chosen', async () => {
        const { host, select } = mountMenu();
        await open(host);

        items()[1]!.click();

        expect(select).not.toHaveBeenCalled();
    });

    it('renders a plain badge without a trigger when not editable', () => {
        const { host } = mountMenu({ priority: 'high', editable: false });

        expect(host.querySelector('button')).toBeNull();
    });
});
