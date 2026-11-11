import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import type { AssistantAttachmentItem } from '@/Composables/useAssistantAttachments';
import AssistantComposer from './AssistantComposer.vue';

function item(): AssistantAttachmentItem {
    return {
        id: 'a1',
        file: new File(['x'], 'bill.pdf', { type: 'application/pdf' }),
        name: 'bill.pdf',
        kind: 'pdf',
        previewUrl: null,
    };
}

function mount(items: AssistantAttachmentItem[] = []) {
    const handlers = { add: vi.fn(), remove: vi.fn(), submit: vi.fn() };
    const composer = ref<{ restore: (text: string) => void } | null>(null);
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp(
        defineComponent({
            render: () =>
                h(AssistantComposer, {
                    ref: composer,
                    items,
                    error: null,
                    disabled: false,
                    sending: false,
                    isFull: false,
                    onAdd: handlers.add,
                    onRemove: handlers.remove,
                    onSubmit: handlers.submit,
                }),
        }),
    )
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    const textarea = host.querySelector('textarea') as HTMLTextAreaElement;
    const send = host.querySelector(`button[aria-label="${sk.assistant_send}"]`) as HTMLElement;

    return { host, handlers, composer, textarea, send };
}

function paste(textarea: HTMLTextAreaElement, files: File[], text = ''): Event {
    const event = new Event('paste', { bubbles: true, cancelable: true });
    Object.defineProperty(event, 'clipboardData', { value: { files, getData: () => text } });
    textarea.dispatchEvent(event);

    return event;
}

describe('AssistantComposer', () => {
    it('emits add and prevents default when the clipboard carries a file', () => {
        const { textarea, handlers } = mount();
        const file = new File(['x'], 'shot.png', { type: 'image/png' });

        const event = paste(textarea, [file]);

        expect(event.defaultPrevented).toBe(true);
        expect(handlers.add).toHaveBeenCalledWith([file]);
    });

    it('keeps the text paste and still adds the files when the clipboard has both', () => {
        const { textarea, handlers } = mount();
        const file = new File(['x'], 'cell.png', { type: 'image/png' });

        const event = paste(textarea, [file], 'A1\tB1');

        expect(event.defaultPrevented).toBe(false);
        expect(handlers.add).toHaveBeenCalledWith([file]);
    });

    it('lets a plain text paste through', () => {
        const { textarea, handlers } = mount();

        const event = paste(textarea, []);

        expect(event.defaultPrevented).toBe(false);
        expect(handlers.add).not.toHaveBeenCalled();
    });

    it('disables send without text and files, and enables it with a file only', () => {
        expect(mount().send.hasAttribute('disabled')).toBe(true);
        expect(mount([item()]).send.hasAttribute('disabled')).toBe(false);
    });

    it('submits the text and clears the field', async () => {
        const { textarea, send, handlers } = mount();
        textarea.value = 'hello';
        textarea.dispatchEvent(new Event('input'));
        await nextTick();

        send.click();
        await nextTick();

        expect(handlers.submit).toHaveBeenCalledWith('hello');
        expect(textarea.value).toBe('');
    });

    it('emits remove with the id of the chip', () => {
        const { host, handlers } = mount([item()]);

        (
            host.querySelector(
                `button[aria-label="${sk.assistant_attachment_remove.replace('{name}', 'bill.pdf')}"]`,
            ) as HTMLElement
        ).click();

        expect(handlers.remove).toHaveBeenCalledWith('a1');
    });

    it('restore refills the textarea', async () => {
        const { composer, textarea } = mount();

        composer.value!.restore('x');
        await nextTick();

        expect(textarea.value).toBe('x');
    });
});
