import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import TimeInput from './TimeInput.vue';

let app: App | null = null;

function mountInput(props: Record<string, unknown> = {}) {
    const emitted: Array<string | null> = [];
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(TimeInput, {
                label: 'Od',
                modelValue: null,
                ...props,
                'onUpdate:modelValue': (v: string | null) => emitted.push(v),
            }),
    });
    app.mount(host);

    return { host, emitted, input: host.querySelector<HTMLInputElement>('input')! };
}

async function type(input: HTMLInputElement, value: string, event: 'blur' | 'enter' = 'blur') {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    if (event === 'blur') input.dispatchEvent(new Event('blur'));
    else input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    await nextTick();
}

describe('TimeInput', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('is a typed 24-hour text field showing the model value', () => {
        const { input } = mountInput({ modelValue: '17:30:00' });

        expect(input.type).toBe('text');
        expect(input.value).toBe('17:30');
        expect(input.placeholder).toBe('time_input_placeholder');
    });

    it('commits 930 as 09:30 on blur and re-formats', async () => {
        const { input, emitted } = mountInput();

        await type(input, '930');

        expect(emitted).toEqual(['09:30']);
        expect(input.value).toBe('09:30');
    });

    it('commits on Enter', async () => {
        const { input, emitted } = mountInput();

        await type(input, '7', 'enter');

        expect(emitted).toEqual(['07:00']);
    });

    it('does not emit per keystroke', async () => {
        const { input, emitted } = mountInput();

        input.value = '9';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.value = '93';
        input.dispatchEvent(new Event('input', { bubbles: true }));

        expect(emitted).toEqual([]);
    });

    it('shows a local error and does not emit for 25:00', async () => {
        const { host, input, emitted } = mountInput();

        await type(input, '25:00');

        expect(emitted).toEqual([]);
        expect(host.textContent).toContain('date_picker_time_invalid');
        expect(input.getAttribute('aria-invalid')).toBe('true');
    });

    it('emits null and keeps the typed text for a stale time replaced by garbage', async () => {
        const { host, input, emitted } = mountInput({ modelValue: '09:30' });

        await type(input, '25:00');

        expect(emitted).toEqual([null]);
        expect(input.value).toBe('25:00');
        expect(host.textContent).toContain('date_picker_time_invalid');
    });

    it('lets Enter submit the form for a valid time and blocks it for an invalid one', () => {
        const { input } = mountInput();
        const enter = () => new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });

        input.value = '930';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        const valid = enter();
        input.dispatchEvent(valid);
        expect(valid.defaultPrevented).toBe(false);

        input.value = 'x';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        const invalid = enter();
        input.dispatchEvent(invalid);
        expect(invalid.defaultPrevented).toBe(true);
    });

    it('rejects a time before min', async () => {
        const { host, input, emitted } = mountInput({ min: '08:00' });

        await type(input, '730');

        expect(emitted).toEqual([]);
        expect(host.textContent).toContain('time_input_out_of_range');
    });

    it('emits null for an empty field and nothing when unchanged', async () => {
        const { input, emitted } = mountInput({ modelValue: '09:30' });

        await type(input, '0930');
        expect(emitted).toEqual([]);

        await type(input, '');
        expect(emitted).toEqual([null]);
    });

    it('lets an outside error win over the local one', async () => {
        const { host, input } = mountInput({ error: 'Server error' });

        await type(input, 'abc');

        expect(host.textContent).toContain('Server error');
        expect(host.textContent).not.toContain('date_picker_time_invalid');
    });

    it('reverts the text on Escape', async () => {
        const { input } = mountInput({ modelValue: '09:30' });

        input.value = 'xx';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();

        expect(input.value).toBe('09:30');
    });
});
