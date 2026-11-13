import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import TaskTitleField from './TaskTitleField.vue';

let app: App | null = null;

function mountField(title = 'Pôvodný názov') {
    const save = vi.fn();
    const state = reactive({ title });
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () => h(TaskTitleField, { title: state.title, editable: true, disabled: false, onSave: save }),
    });
    app.mount(host);
    const textarea = host.querySelector<HTMLTextAreaElement>('textarea')!;

    return { textarea, save, state };
}

async function type(textarea: HTMLTextAreaElement, value: string) {
    textarea.focus();
    textarea.value = value;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();
}

function key(textarea: HTMLTextAreaElement, name: string) {
    textarea.dispatchEvent(new KeyboardEvent('keydown', { key: name, bubbles: true, cancelable: true }));
}

describe('TaskTitleField', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('saves the trimmed title on Enter without a line break', async () => {
        const { textarea, save } = mountField();

        await type(textarea, '  Nový názov  ');
        key(textarea, 'Enter');
        await nextTick();

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith('Nový názov');
        expect(textarea.value).not.toContain('\n');
    });

    it('collapses a pasted line break into a space', async () => {
        const { textarea, save } = mountField();

        await type(textarea, 'Prvý\nDruhý');
        textarea.blur();
        await nextTick();

        expect(save).toHaveBeenCalledWith('Prvý Druhý');
    });

    it('restores the prop value on Escape and emits nothing', async () => {
        const { textarea, save } = mountField();

        await type(textarea, 'Rozpísané');
        key(textarea, 'Escape');
        await nextTick();

        expect(textarea.value).toBe('Pôvodný názov');
        expect(save).not.toHaveBeenCalled();
    });

    it('saves a changed value on blur', async () => {
        const { textarea, save } = mountField();

        await type(textarea, 'Zmenený');
        textarea.blur();
        await nextTick();

        expect(save).toHaveBeenCalledWith('Zmenený');
    });

    it('emits nothing on blur when the value is unchanged', async () => {
        const { textarea, save } = mountField();

        textarea.focus();
        textarea.blur();
        await nextTick();

        expect(save).not.toHaveBeenCalled();
    });

    it('keeps a dirty draft when the prop updates while focused', async () => {
        const { textarea, state } = mountField();

        await type(textarea, 'Moja úprava');
        state.title = 'Zmena zo servera';
        await nextTick();
        await nextTick();

        expect(textarea.value).toBe('Moja úprava');
    });
});
