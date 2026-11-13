import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));

import CalendarGrid from './CalendarGrid.vue';

let app: App | null = null;

function mountGrid(props: { modelValue: string | null; min?: string; max?: string }) {
    const emitted: string[] = [];
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(CalendarGrid, {
                label: 'Dátum',
                ...props,
                'onUpdate:modelValue': (v: string) => emitted.push(v),
            }),
    });
    app.mount(host);

    return { host, emitted };
}

const day = (host: HTMLElement, iso: string) => host.querySelector<HTMLElement>(`[data-day="${iso}"]`)!;

describe('CalendarGrid', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('renders a labelled grid of 42 days, Monday first, in the UI language', () => {
        const { host } = mountGrid({ modelValue: '2026-11-13' });

        const grid = host.querySelector('[role="grid"]')!;
        expect(grid.getAttribute('aria-label')).toBe('Dátum');
        expect(host.querySelectorAll('[role="gridcell"]')).toHaveLength(42);
        expect(host.querySelectorAll('[role="columnheader"]')[0]!.textContent!.trim()).toBe('po');
        expect(host.querySelector('[aria-live="polite"]')!.textContent).toContain('november');
        expect(host.querySelector('[data-day]')!.getAttribute('data-day')).toBe('2026-10-26');
    });

    it('marks the selected day and keeps one tabbable day', () => {
        const { host } = mountGrid({ modelValue: '2026-11-13' });

        expect(day(host, '2026-11-13').parentElement!.getAttribute('aria-selected')).toBe('true');
        expect(host.querySelectorAll('button[tabindex="0"][data-day]')).toHaveLength(1);
        expect(day(host, '2026-11-13').hasAttribute('data-autofocus')).toBe(true);
    });

    it('emits the picked day as yyyy-MM-dd', () => {
        const { host, emitted } = mountGrid({ modelValue: '2026-11-13' });

        day(host, '2026-11-20').click();

        expect(emitted).toEqual(['2026-11-20']);
    });

    it('does not emit for a day outside min and max', () => {
        const { host, emitted } = mountGrid({ modelValue: '2026-11-13', min: '2026-11-10', max: '2026-11-20' });

        expect(day(host, '2026-11-09').getAttribute('aria-disabled')).toBe('true');
        day(host, '2026-11-09').click();
        day(host, '2026-11-21').click();
        day(host, '2026-11-15').click();

        expect(emitted).toEqual(['2026-11-15']);
    });

    it('moves focus with the arrow keys and across months with PageDown', async () => {
        const { host } = mountGrid({ modelValue: '2026-11-13' });

        day(host, '2026-11-13').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        await nextTick();
        expect(document.activeElement).toBe(day(host, '2026-11-14'));

        day(host, '2026-11-14').dispatchEvent(new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true }));
        await nextTick();
        expect(host.querySelector('[aria-live="polite"]')!.textContent).toContain('december');
        expect(document.activeElement).toBe(day(host, '2026-12-14'));
    });

    it('steps the month with the header buttons', async () => {
        const { host } = mountGrid({ modelValue: '2026-11-13' });

        host.querySelector<HTMLElement>('button[aria-label="date_picker_next_month"]')!.click();
        await nextTick();

        expect(host.querySelector('[aria-live="polite"]')!.textContent).toContain('december');
    });
});
