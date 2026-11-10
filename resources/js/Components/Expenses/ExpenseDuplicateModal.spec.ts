import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import ExpenseDuplicateModal from './ExpenseDuplicateModal.vue';

function mount(open: boolean) {
    const handlers = { keepBoth: vi.fn(), replace: vi.fn(), cancel: vi.fn() };
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp(
        defineComponent({
            render: () =>
                h(ExpenseDuplicateModal, {
                    open,
                    messages: ['Anna Test 10/2026: 900.00'],
                    processing: false,
                    onKeepBoth: handlers.keepBoth,
                    onReplace: handlers.replace,
                    onCancel: handlers.cancel,
                }),
        }),
    )
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(host);

    return { host, handlers };
}

function button(host: HTMLElement, text: string): HTMLElement {
    return [...host.querySelectorAll('button')].find((b) => b.textContent?.trim() === text) as HTMLElement;
}

describe('ExpenseDuplicateModal', () => {
    it('lists the conflict messages when open', () => {
        const { host } = mount(true);

        expect(host.querySelector('dialog')?.classList.contains('modal-open')).toBe(true);
        expect(host.textContent).toContain('Anna Test 10/2026: 900.00');
    });

    it('is closed when open is false', () => {
        const { host } = mount(false);

        expect(host.querySelector('dialog')?.classList.contains('modal-open')).toBe(false);
    });

    it('emits one event per choice', () => {
        const { host, handlers } = mount(true);

        button(host, 'Uložiť aj tak').click();
        button(host, 'Nahradiť pôvodnú').click();
        button(host, 'Zrušiť').click();

        expect(handlers.keepBoth).toHaveBeenCalledTimes(1);
        expect(handlers.replace).toHaveBeenCalledTimes(1);
        expect(handlers.cancel).toHaveBeenCalledTimes(1);
    });

    it('cancels on Escape', () => {
        const { host, handlers } = mount(true);

        host.querySelector('.modal-box')?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(handlers.cancel).toHaveBeenCalledTimes(1);
    });
});
