import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref } from 'vue';
import type { App } from 'vue';
import { format } from 'date-fns';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: Record<string, string>) =>
            params ? `${key} ${Object.values(params).join(' ')}` : key,
        locale: { value: 'sk' },
    }),
}));

import DateInput from './DateInput.vue';

let app: App | null = null;

function mountInput(props: Record<string, unknown> = {}) {
    const emitted: Array<string | null> = [];
    const model = ref<string | null>((props.modelValue as string | null | undefined) ?? null);
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(DateInput, {
                label: 'Dátum',
                ...props,
                modelValue: model.value,
                'onUpdate:modelValue': (v: string | null) => {
                    emitted.push(v);
                    model.value = v;
                },
            }),
    });
    app.mount(host);

    return { host, emitted, input: host.querySelector<HTMLInputElement>('input[type="text"]')! };
}

async function type(input: HTMLInputElement, value: string, how: 'blur' | 'enter' = 'blur') {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    if (how === 'blur') input.dispatchEvent(new Event('blur'));
    else input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    await nextTick();
}

async function openCalendar(host: HTMLElement) {
    host.querySelector<HTMLElement>('button[aria-label^="date_picker_open"]')!.click();
    await nextTick();
    await nextTick();
}

const panelButton = (label: string) =>
    [...document.body.querySelectorAll<HTMLElement>('[role="dialog"] button')].find(
        (b) => b.textContent!.trim() === label,
    );

describe('DateInput', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('shows the model as d. M. yyyy in a text field', () => {
        const { input } = mountInput({ modelValue: '2026-11-03' });

        expect(input.value).toBe('3. 11. 2026');
        expect(document.body.querySelector('input[type="date"]')).toBeNull();
    });

    it('names the calendar button after the field so several fields stay distinguishable', () => {
        mountInput({ label: 'Dátum vystavenia' });

        const button = document.body.querySelector('button[aria-haspopup="dialog"]')!;
        expect(button.getAttribute('aria-label')).toBe('date_picker_open Dátum vystavenia');
    });

    it('commits a typed date as yyyy-MM-dd and re-formats', async () => {
        const { input, emitted } = mountInput();

        await type(input, '13.11.2026');

        expect(emitted).toEqual(['2026-11-13']);
        expect(input.value).toBe('13. 11. 2026');
    });

    it('fills in the current year for d.m.', async () => {
        const { input, emitted } = mountInput();

        await type(input, '13.11.', 'enter');

        expect(emitted).toEqual([`${new Date().getFullYear()}-11-13`]);
    });

    it('emits null and keeps the typed text and error for a stale date replaced by garbage', async () => {
        const { host, input, emitted } = mountInput({ modelValue: '2026-01-15' });

        await type(input, '32.1.2026');

        expect(emitted).toEqual([null]);
        expect(input.value).toBe('32.1.2026');
        expect(host.textContent).toContain('date_input_invalid');
    });

    it('emits null and keeps the error for a date outside min and max', async () => {
        const { host, input, emitted } = mountInput({ modelValue: '2026-11-10', max: '2026-11-13' });

        await type(input, '14.11.2026');

        expect(emitted).toEqual([null]);
        expect(input.value).toBe('14.11.2026');
        expect(host.textContent).toContain('date_input_out_of_range');
    });

    it('lets Enter submit the form for a valid date and blocks it for an invalid one', async () => {
        const { input } = mountInput();
        const enter = () => new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });

        input.value = '13.11.2026';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        const valid = enter();
        input.dispatchEvent(valid);
        expect(valid.defaultPrevented).toBe(false);

        input.value = 'abc';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        const invalid = enter();
        input.dispatchEvent(invalid);
        expect(invalid.defaultPrevented).toBe(true);
        await nextTick();
    });

    it('shows a local error and does not emit for garbage', async () => {
        const { host, input, emitted } = mountInput();

        await type(input, 'abc');

        expect(emitted).toEqual([]);
        expect(host.textContent).toContain('date_input_invalid');
        expect(input.getAttribute('aria-invalid')).toBe('true');
    });

    it('emits null for an empty field', async () => {
        const { input, emitted } = mountInput({ modelValue: '2026-11-13' });

        await type(input, '');

        expect(emitted).toEqual([null]);
    });

    it('rejects a date after max with the formatted bound', async () => {
        const { host, input, emitted } = mountInput({ max: '2026-11-13' });

        await type(input, '14.11.2026');

        expect(emitted).toEqual([]);
        expect(host.textContent).toContain('date_input_out_of_range ≤ 13. 11. 2026');
    });

    it('does not emit per keystroke and skips an unchanged commit', async () => {
        const { input, emitted } = mountInput({ modelValue: '2026-11-13' });

        input.value = '1';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.value = '13.11.2026';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        expect(emitted).toEqual([]);

        input.dispatchEvent(new Event('blur'));
        expect(emitted).toEqual([]);
    });

    it('lets an outside error win over the local error', async () => {
        const { host, input } = mountInput({ error: 'Server error' });

        await type(input, 'abc');

        expect(host.textContent).toContain('Server error');
        expect(host.textContent).not.toContain('date_input_invalid');
    });

    it('reverts the text on Escape', async () => {
        const { input } = mountInput({ modelValue: '2026-11-13' });

        input.value = 'zzz';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();

        expect(input.value).toBe('13. 11. 2026');
    });

    it('emits and closes when a calendar day is clicked', async () => {
        const { host, emitted } = mountInput({ modelValue: '2026-11-13' });
        await openCalendar(host);

        document.body.querySelector<HTMLElement>('[data-day="2026-11-20"]')!.click();
        await nextTick();

        expect(emitted).toEqual(['2026-11-20']);
        expect(document.body.querySelector('[role="grid"]')).toBeNull();
    });

    it('offers Dnes and, unless required, Vymazať', async () => {
        const { host, emitted } = mountInput({ modelValue: '2026-11-13' });
        await openCalendar(host);

        panelButton('date_picker_today')!.click();
        await nextTick();
        expect(emitted).toEqual([format(new Date(), 'yyyy-MM-dd')]);

        await openCalendar(host);
        panelButton('date_picker_clear')!.click();
        await nextTick();
        expect(emitted[1]).toBeNull();
    });

    it('hides Vymazať when required and disables Dnes outside min and max', async () => {
        const { host } = mountInput({ modelValue: '2026-11-13', required: true, max: '2000-01-01' });
        await openCalendar(host);

        expect(panelButton('date_picker_clear')).toBeUndefined();
        expect(panelButton('date_picker_today')!.hasAttribute('disabled')).toBe(true);
    });
});
