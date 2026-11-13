import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, type App } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../../lang/sk/app.json';
import { HttpError, deleteJson, getJson, patchJson } from '@/utils/http';
import AssistantHistory from './AssistantHistory.vue';

const chat = vi.hoisted(() => ({
    state: { open: true },
    load: vi.fn(),
    retitle: vi.fn(),
    forgetIfOpen: vi.fn(),
}));

vi.mock('@/Composables/useAssistantChat', () => ({ useAssistantChat: () => chat }));
vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getJson: vi.fn(),
    patchJson: vi.fn(),
    deleteJson: vi.fn(),
}));

const get = vi.mocked(getJson);
const patch = vi.mocked(patchJson);
const del = vi.mocked(deleteJson);

function item(id: string, title: string) {
    return { id, title, last_message_at: '2026-11-13T10:00:00Z', created_at: '2026-11-13T09:00:00Z' };
}

type Btn = InstanceType<typeof window.HTMLButtonElement>;

const mounted: { app: App; host: HTMLElement }[] = [];

async function flush(): Promise<void> {
    for (let i = 0; i < 5; i++) await nextTick();
    await new Promise((resolve) => setTimeout(resolve));
    await nextTick();
}

async function mount(activeId: string | null = null) {
    const host = document.createElement('div');
    document.body.appendChild(host);
    const onOpened = vi.fn();
    const app = createApp(defineComponent({ render: () => h(AssistantHistory, { activeId, onOpened }) })).use(
        createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }),
    );
    app.mount(host);
    mounted.push({ app, host });
    await flush();

    return { host, onOpened };
}

function button(label: string): Btn {
    const found = Array.from(document.body.querySelectorAll('button')).find((b) => b.textContent?.trim() === label);
    if (!found) throw new Error(`no button ${label}`);

    return found;
}

beforeEach(() => {
    get.mockReset();
    patch.mockReset();
    del.mockReset();
    chat.load.mockReset();
    chat.retitle.mockReset();
    chat.forgetIfOpen.mockReset();
    chat.state.open = true;
});

afterEach(() => {
    mounted.splice(0).forEach(({ app, host }) => {
        app.unmount();
        host.remove();
    });
    document.body.innerHTML = '';
});

function menuItem(label: string): Btn {
    const found = Array.from(document.body.querySelectorAll<Btn>('[role="menuitem"]')).find(
        (b) => b.textContent?.trim() === label,
    );
    if (!found) throw new Error(`no menu item ${label}`);

    return found;
}

async function openMenuOf(host: HTMLElement, id: string): Promise<void> {
    const row = host.querySelector(`[data-conversation-open="${id}"]`)!.parentElement!;
    row.querySelector<Btn>('[aria-haspopup="menu"]')!.click();
    await flush();
}

describe('AssistantHistory', () => {
    it('shows loading, then rows, and marks the active one', async () => {
        get.mockResolvedValueOnce({
            data: [item('a', 'Faktúry'), item('b', 'Úlohy')],
            meta: { current_page: 1, last_page: 1 },
        });
        const { host } = await mount('b');

        expect(host.textContent).toContain('Faktúry');
        expect(host.querySelector('[data-conversation-open="b"]')!.getAttribute('aria-current')).toBe('true');
        expect(host.querySelector('[data-conversation-open="a"]')!.hasAttribute('aria-current')).toBe(false);
    });

    it('shows the empty state', async () => {
        get.mockResolvedValueOnce({ data: [], meta: { current_page: 1, last_page: 1 } });
        const { host } = await mount();

        expect(host.textContent).toContain(sk.assistant_history_empty);
    });

    it('shows the error with a retry button that reloads', async () => {
        get.mockRejectedValueOnce(new HttpError(500)).mockResolvedValueOnce({
            data: [item('a', 'Faktúry')],
            meta: { current_page: 1, last_page: 1 },
        });
        const { host } = await mount();

        expect(host.textContent).toContain(sk.assistant_history_load_failed);
        button(sk.retry).click();
        await flush();

        expect(host.textContent).toContain('Faktúry');
    });

    it('opens a conversation through the chat and emits opened', async () => {
        get.mockResolvedValueOnce({ data: [item('a', 'Faktúry')], meta: { current_page: 1, last_page: 1 } });
        const { host, onOpened } = await mount();

        host.querySelector<Btn>('[data-conversation-open="a"]')!.click();

        expect(chat.load).toHaveBeenCalledWith('a');
        expect(onOpened).toHaveBeenCalled();
    });

    it('offers rename and delete in the row menu, and rename retitles the chat', async () => {
        get.mockResolvedValueOnce({ data: [item('a', 'Faktúry')], meta: { current_page: 1, last_page: 1 } });
        patch.mockResolvedValueOnce(item('a', 'Nový'));
        const { host } = await mount('a');

        await openMenuOf(host, 'a');
        expect(menuItem(sk.assistant_conversation_delete)).toBeTruthy();
        menuItem(sk.assistant_conversation_rename).click();
        await flush();

        const input = host.querySelector<HTMLInputElement>('input')!;
        expect(input.getAttribute('aria-label')).toBe(sk.assistant_conversation_title_label);
        input.value = 'Nový';
        input.dispatchEvent(new Event('input'));
        host.querySelector('form:not(.modal-backdrop)')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();

        expect(patch).toHaveBeenCalledWith('/assistant/conversations/a', { title: 'Nový' });
        expect(chat.retitle).toHaveBeenCalledWith('a', 'Nový');
        expect(host.textContent).toContain('Nový');
        expect(host.querySelector('form:not(.modal-backdrop)')).toBeNull();
    });

    it('shows a rename 422 under the field', async () => {
        get.mockResolvedValueOnce({ data: [item('a', 'Faktúry')], meta: { current_page: 1, last_page: 1 } });
        patch.mockRejectedValueOnce(new HttpError(422, null, null, { title: ['Príliš dlhý'] }));
        const { host } = await mount();

        await openMenuOf(host, 'a');
        menuItem(sk.assistant_conversation_rename).click();
        await flush();
        host.querySelector('form:not(.modal-backdrop)')!.dispatchEvent(new Event('submit', { cancelable: true }));
        await flush();

        expect(host.textContent).toContain('Príliš dlhý');
        expect(chat.retitle).not.toHaveBeenCalled();
    });

    it('deletes after the confirm modal and tells the chat', async () => {
        get.mockResolvedValueOnce({ data: [item('a', 'Faktúry')], meta: { current_page: 1, last_page: 1 } });
        del.mockResolvedValueOnce(undefined);
        const { host } = await mount('a');

        await openMenuOf(host, 'a');
        menuItem(sk.assistant_conversation_delete).click();
        await flush();
        expect(del).not.toHaveBeenCalled();

        document.body.querySelector<Btn>('.modal-action .btn-error')!.click();
        await flush();

        expect(del).toHaveBeenCalledWith('/assistant/conversations/a');
        expect(chat.forgetIfOpen).toHaveBeenCalledWith('a');
        expect(host.querySelector('[data-conversation-open="a"]')).toBeNull();
    });

    it('Esc in the rename input does not reach the panel', async () => {
        get.mockResolvedValueOnce({ data: [item('a', 'Faktúry')], meta: { current_page: 1, last_page: 1 } });
        const { host } = await mount();
        const closePanel = vi.fn(() => {
            chat.state.open = false;
        });
        host.addEventListener('keydown', closePanel);

        await openMenuOf(host, 'a');
        menuItem(sk.assistant_conversation_rename).click();
        await flush();
        host.querySelector('input')!.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flush();

        expect(closePanel).not.toHaveBeenCalled();
        expect(chat.state.open).toBe(true);
        expect(host.querySelector('form:not(.modal-backdrop)')).toBeNull();
    });
});
