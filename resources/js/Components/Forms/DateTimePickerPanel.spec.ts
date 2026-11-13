import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref } from 'vue';
import type { App } from 'vue';
import { addDays, format } from 'date-fns';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));

import DateTimePickerPanel from './DateTimePickerPanel.vue';

let app: App | null = null;

function mountPanel(initial = '') {
    const events: Array<[string, unknown?]> = [];
    const model = ref(initial);
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(DateTimePickerPanel, {
                label: 'Termín',
                modelValue: model.value,
                'onUpdate:modelValue': (v: string) => {
                    events.push(['update', v]);
                    model.value = v;
                },
                onCommit: (v: string) => events.push(['commit', v]),
                onClear: () => events.push(['clear']),
            }),
    });
    app.mount(host);

    return { host, events };
}

const button = (host: HTMLElement, label: string) =>
    [...host.querySelectorAll<HTMLElement>('button')].find((b) => b.textContent!.trim() === label)!;

describe('DateTimePickerPanel', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('defaults the time to 12:00 when a day is picked on an empty value', () => {
        const { host, events } = mountPanel();

        host.querySelector<HTMLElement>('[data-day]:not(.kg-calendar-day--outside)')!.click();

        const [, value] = events[0]!;
        expect(String(value)).toMatch(/^\d{4}-\d{2}-\d{2}T12:00$/);
    });

    it('keeps an existing time and does not commit on a day click', () => {
        const { host, events } = mountPanel('2026-11-13T09:30');

        host.querySelector<HTMLElement>('[data-day="2026-11-20"]')!.click();

        expect(events).toEqual([['update', '2026-11-20T09:30']]);
    });

    it('commits quick picks with the kept time', () => {
        const { host, events } = mountPanel('2026-11-13T09:30');
        const tomorrow = format(addDays(new Date(), 1), 'yyyy-MM-dd');

        button(host, 'date_picker_tomorrow').click();

        expect(events).toEqual([['commit', `${tomorrow}T09:30`]]);
    });

    it('commits Dnes and Budúci pondelok with 12:00 on an empty value', () => {
        const { host, events } = mountPanel();

        button(host, 'date_picker_today').click();
        button(host, 'date_picker_next_monday').click();

        expect(events).toHaveLength(2);
        expect(String(events[0]![1])).toBe(`${format(new Date(), 'yyyy-MM-dd')}T12:00`);
        const monday = new Date(String(events[1]![1]).slice(0, 10) + 'T00:00');
        expect(monday.getDay()).toBe(1);
        expect(monday.getTime()).toBeGreaterThan(Date.now());
    });

    it('emits the typed time on the kept date', async () => {
        const { host, events } = mountPanel('2026-11-13T09:30');
        const input = host.querySelector<HTMLInputElement>('input[type="text"]')!;

        input.value = '1745';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(events).toEqual([['update', '2026-11-13T17:45']]);
    });

    it('shows Vymazať only with a value and emits clear', () => {
        const empty = mountPanel();
        expect(button(empty.host, 'date_picker_clear')).toBeUndefined();
        app?.unmount();
        document.body.innerHTML = '';

        const { host, events } = mountPanel('2026-11-13T09:30');
        button(host, 'date_picker_clear').click();

        expect(events).toEqual([['clear']]);
    });

    it('disables the time field without a date', () => {
        const { host } = mountPanel();

        expect(host.querySelector<HTMLInputElement>('input[type="text"]')!.disabled).toBe(true);
    });
});
