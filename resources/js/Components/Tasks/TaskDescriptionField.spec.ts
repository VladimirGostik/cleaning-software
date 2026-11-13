import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';
import type { App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import TaskDescriptionField from './TaskDescriptionField.vue';

let app: App | null = null;

function mountField(description: string | null = null, editable = true) {
    const save = vi.fn();
    const state = reactive({ description });
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(TaskDescriptionField, { description: state.description, editable, disabled: false, onSave: save }),
    });
    app.mount(host);

    return { host, save, state, textarea: host.querySelector<HTMLTextAreaElement>('textarea') };
}

async function type(textarea: HTMLTextAreaElement, value: string) {
    textarea.focus();
    textarea.value = value;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    await nextTick();
}

function blur(textarea: HTMLTextAreaElement) {
    textarea.dispatchEvent(new Event('focusout', { bubbles: true }));
}

describe('TaskDescriptionField', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('emits a debounced save while typing', async () => {
        const { textarea, save } = mountField();

        await type(textarea!, 'Umyť okná');

        expect(save).toHaveBeenCalledWith('Umyť okná', true);
    });

    it('emits one immediate save on blur and clears an empty description to null', async () => {
        const { textarea, save } = mountField('Starý popis');

        await type(textarea!, '');
        save.mockClear();
        blur(textarea!);

        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith(null, false);
    });

    it('does not send again on blur once the saved value came back from the server', async () => {
        const { textarea, save, state } = mountField();

        await type(textarea!, 'Nový popis');
        state.description = 'Nový popis';
        await nextTick();
        save.mockClear();
        blur(textarea!);

        expect(save).not.toHaveBeenCalled();
    });

    it('does not emit on blur when nothing changed', () => {
        const { textarea, save } = mountField('Popis');

        blur(textarea!);

        expect(save).not.toHaveBeenCalled();
    });

    it('renders plain text without a textarea when not editable', () => {
        const { host, textarea } = mountField('Popis', false);

        expect(textarea).toBeNull();
        expect(host.textContent).toContain('Popis');
    });

    it('shows the placeholder on the empty editable textarea', () => {
        const { textarea } = mountField(null);

        expect(textarea!.getAttribute('placeholder')).toBe('task_add_description');
    });

    it('takes exactly its text height when filled and keeps a small minimum when empty', async () => {
        const { textarea, state } = mountField(null);
        expect(textarea!.getAttribute('rows')).toBe('3');

        state.description = 'Jeden riadok';
        await nextTick();
        await nextTick();

        expect(textarea!.getAttribute('rows')).toBe('1');
    });
});
