import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import sk from '../../lang/sk/app.json';
import { HttpError, deleteJson, getJson, patchJson } from '@/utils/http';
import { useAssistantConversations } from './useAssistantConversations';

vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    getJson: vi.fn(),
    patchJson: vi.fn(),
    deleteJson: vi.fn(),
}));

const get = vi.mocked(getJson);
const patch = vi.mocked(patchJson);
const del = vi.mocked(deleteJson);

function item(id: string, title = id): App.Data.Ai.AssistantConversationItemData {
    return { id, title, last_message_at: '2026-11-13T10:00:00Z', created_at: '2026-11-13T09:00:00Z' };
}

function page(ids: string[], current: number, last: number) {
    return { data: ids.map((id) => item(id)), meta: { current_page: current, last_page: last } };
}

function setup() {
    let api!: ReturnType<typeof useAssistantConversations>;
    createApp(
        defineComponent({
            setup() {
                api = useAssistantConversations();
                return () => h('div');
            },
        }),
    )
        .use(createI18n({ legacy: false, locale: 'sk', messages: { sk }, missingWarn: false, fallbackWarn: false }))
        .mount(document.createElement('div'));

    return api;
}

beforeEach(() => {
    get.mockReset();
    patch.mockReset();
    del.mockReset();
});

describe('useAssistantConversations', () => {
    it('load replaces items and hasMore comes from meta', async () => {
        const api = setup();
        get.mockResolvedValueOnce(page(['a', 'b'], 1, 2));

        await api.load();

        expect(api.items.value.map((i) => i.id)).toEqual(['a', 'b']);
        expect(api.hasMore.value).toBe(true);
        expect(api.loading.value).toBe(false);
    });

    it('load failure sets error', async () => {
        const api = setup();
        get.mockRejectedValueOnce(new HttpError(500));

        await api.load();

        expect(api.error.value).toBe(true);
    });

    it('loadMore appends the next page and de-duplicates by id', async () => {
        const api = setup();
        get.mockResolvedValueOnce(page(['a', 'b'], 1, 2)).mockResolvedValueOnce(page(['b', 'c'], 2, 2));

        await api.load();
        await api.loadMore();

        expect(get.mock.calls[1][0]).toBe('/assistant/conversations?page=2');
        expect(api.items.value.map((i) => i.id)).toEqual(['a', 'b', 'c']);
        expect(api.hasMore.value).toBe(false);
    });

    it('rename replaces the item and returns null', async () => {
        const api = setup();
        get.mockResolvedValueOnce(page(['a'], 1, 1));
        await api.load();
        patch.mockResolvedValueOnce(item('a', 'New'));

        await expect(api.rename('a', 'New')).resolves.toBeNull();

        expect(patch).toHaveBeenCalledWith('/assistant/conversations/a', { title: 'New' });
        expect(api.items.value[0].title).toBe('New');
    });

    it('rename returns errors.title[0] on 422 and keeps the old title', async () => {
        const api = setup();
        get.mockResolvedValueOnce(page(['a'], 1, 1));
        await api.load();
        patch.mockRejectedValueOnce(new HttpError(422, null, null, { title: ['Too long'] }));

        await expect(api.rename('a', 'x')).resolves.toBe('Too long');

        expect(api.items.value[0].title).toBe('a');
        expect(api.busyId.value).toBeNull();
    });

    it('rename returns the generic text on another failure', async () => {
        const api = setup();
        patch.mockRejectedValueOnce(new HttpError(500));

        await expect(api.rename('a', 'x')).resolves.toBe(sk.assistant_conversation_rename_failed);
    });

    it('remove drops the item on 204 and on 404, keeps it on 500', async () => {
        const api = setup();
        get.mockResolvedValueOnce(page(['a', 'b', 'c'], 1, 1));
        await api.load();

        del.mockResolvedValueOnce(undefined);
        await expect(api.remove('a')).resolves.toBe(true);
        del.mockRejectedValueOnce(new HttpError(404));
        await expect(api.remove('b')).resolves.toBe(true);
        del.mockRejectedValueOnce(new HttpError(500));
        await expect(api.remove('c')).resolves.toBe(false);

        expect(api.items.value.map((i) => i.id)).toEqual(['c']);
    });
});
