import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, string>) => (params ? `${key}:${params.company}` : key),
    }),
}));

import TaskCompanyMenu from './TaskCompanyMenu.vue';

let app: App | null = null;

const OPTIONS = [
    { value: null, label: 'Globálna' },
    { value: 'tA', label: 'Alfa' },
];

function mountMenu(overrides: Record<string, unknown> = {}) {
    const state = reactive({ modelValue: null as string | null, locked: false, options: OPTIONS, ...overrides });
    const onUpdate = vi.fn((value: string | null) => {
        state.modelValue = value;
    });
    const onOpen = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(TaskCompanyMenu, {
                modelValue: state.modelValue,
                options: state.options,
                locked: state.locked,
                lockedLabel: 'locked-explanation',
                'onUpdate:modelValue': onUpdate,
                'onUpdate:open': onOpen,
            }),
    });
    app.mount(host);

    return { host, onUpdate, onOpen, state };
}

function menuItems(): HTMLElement[] {
    return Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitemradio"]'));
}

describe('TaskCompanyMenu', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('opens a radio menu from the trigger and reports the open state', async () => {
        const { host, onOpen } = mountMenu();
        const trigger = host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!;

        expect(trigger.getAttribute('aria-label')).toBe('task_change_company:Globálna');

        trigger.click();
        await nextTick();
        await nextTick();

        expect(onOpen).toHaveBeenCalledWith(true);
        expect(menuItems().map((item) => item.getAttribute('aria-checked'))).toEqual(['true', 'false']);
    });

    it('moves between items with the arrow keys and wraps', async () => {
        const { host } = mountMenu();
        host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
        await nextTick();
        await nextTick();

        const [first, second] = menuItems();
        first.focus();
        first.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(second);

        second.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(first);

        first.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(second);

        second.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(first);
    });

    it('emits the chosen company, closes, and stays silent for the current one', async () => {
        const { host, onUpdate, onOpen } = mountMenu();
        host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
        await nextTick();
        await nextTick();

        menuItems()[0].click();
        await nextTick();

        expect(onUpdate).not.toHaveBeenCalled();
        expect(onOpen).toHaveBeenLastCalledWith(false);

        host.querySelector<HTMLElement>('button[aria-haspopup="menu"]')!.click();
        await nextTick();
        await nextTick();
        menuItems()[1].click();
        await nextTick();

        expect(onUpdate).toHaveBeenCalledWith('tA');
    });

    it('renders plain text without a menu when there is only one option', () => {
        const { host } = mountMenu({ options: [OPTIONS[0]] });

        expect(host.querySelector('button')).toBeNull();
        expect(host.textContent).toBe('Globálna');
    });

    it('renders the locked company as text with a hidden icon and a screen reader explanation', () => {
        const { host } = mountMenu({ locked: true, modelValue: 'tA' });

        expect(host.querySelector('button')).toBeNull();
        expect(host.textContent).toContain('Alfa');
        expect(host.querySelector('.sr-only')?.textContent).toBe('locked-explanation');
        expect(host.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
        expect(host.querySelector('[role="img"]')).toBeNull();
    });
});
