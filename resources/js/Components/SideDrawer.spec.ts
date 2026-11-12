import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type App } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

import SideDrawer from './SideDrawer.vue';

function mockWide(matches: boolean): void {
    window.matchMedia = vi.fn().mockImplementation(() => ({
        matches,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
    })) as unknown as typeof window.matchMedia;
}

let app: App | null = null;

async function mount(props: { modal?: boolean }, onClose = vi.fn()) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({ render: () => h(SideDrawer, { open: true, title: 'T', onClose, ...props }) });
    app.mount(host);
    await nextTick();

    return onClose;
}

beforeEach(() => {
    document.body.style.overflow = '';
});

afterEach(() => {
    app?.unmount();
    app = null;
    document.body.innerHTML = '';
});

describe('SideDrawer', () => {
    it('is a modal with a backdrop and scroll lock by default', async () => {
        mockWide(true);
        await mount({});

        expect(document.querySelector('[aria-hidden="true"].fixed.inset-0')).not.toBeNull();
        expect(document.querySelector('aside')?.getAttribute('aria-modal')).toBe('true');
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('is non-modal at lg and above with modal=false', async () => {
        mockWide(true);
        const onClose = await mount({ modal: false });

        expect(document.querySelector('[aria-hidden="true"].fixed.inset-0')).toBeNull();
        expect(document.querySelector('aside')?.getAttribute('aria-modal')).toBe('false');
        expect(document.body.style.overflow).toBe('');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(onClose).toHaveBeenCalledOnce();
    });

    it('lets Esc inside a modal drawer close it and bubble as before', async () => {
        mockWide(true);
        const onClose = await mount({});
        const bubbled = vi.fn();
        document.addEventListener('keydown', bubbled);

        document
            .querySelector('aside')
            ?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        document.removeEventListener('keydown', bubbled);

        expect(onClose).toHaveBeenCalledOnce();
        expect(bubbled).toHaveBeenCalledOnce();
    });

    it('stays a modal below lg even with modal=false', async () => {
        mockWide(false);
        await mount({ modal: false });

        expect(document.querySelector('[aria-hidden="true"].fixed.inset-0')).not.toBeNull();
        expect(document.querySelector('aside')?.getAttribute('aria-modal')).toBe('true');
        expect(document.body.style.overflow).toBe('hidden');
    });
});
