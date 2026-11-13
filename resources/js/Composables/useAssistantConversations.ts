import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { HttpError, deleteJson, getJson, patchJson } from '@/utils/http';
import type { AssistantConversationPage } from '@/utils/assistant';

const ENDPOINT = '/assistant/conversations';

export function useAssistantConversations() {
    const { t } = useI18n();
    const items = ref<App.Data.Ai.AssistantConversationItemData[]>([]);
    const loading = ref(false);
    const loadingMore = ref(false);
    const error = ref(false);
    const busyId = ref<string | null>(null);
    const page = ref(1);
    const lastPage = ref(1);
    const hasMore = computed(() => page.value < lastPage.value);

    async function load(): Promise<void> {
        loading.value = true;
        error.value = false;

        try {
            const result = await getJson<AssistantConversationPage>(ENDPOINT);
            items.value = result.data;
            page.value = result.meta.current_page;
            lastPage.value = result.meta.last_page;
        } catch {
            error.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function loadMore(): Promise<void> {
        if (!hasMore.value || loadingMore.value || loading.value) return;
        loadingMore.value = true;

        try {
            const result = await getJson<AssistantConversationPage>(`${ENDPOINT}?page=${page.value + 1}`);
            const known = new Set(items.value.map((item) => item.id));
            items.value = [...items.value, ...result.data.filter((item) => !known.has(item.id))];
            page.value = result.meta.current_page;
            lastPage.value = result.meta.last_page;
        } catch {
            error.value = true;
        } finally {
            loadingMore.value = false;
        }
    }

    async function rename(id: string, title: string): Promise<string | null> {
        busyId.value = id;

        try {
            const updated = await patchJson<App.Data.Ai.AssistantConversationItemData>(`${ENDPOINT}/${id}`, {
                title,
            });
            items.value = items.value.map((item) => (item.id === id ? updated : item));

            return null;
        } catch (e) {
            const message = e instanceof HttpError ? e.errors?.title?.[0] : undefined;

            return message ?? t('assistant_conversation_rename_failed');
        } finally {
            busyId.value = null;
        }
    }

    async function remove(id: string): Promise<boolean> {
        busyId.value = id;

        try {
            await deleteJson(`${ENDPOINT}/${id}`);
        } catch (e) {
            if (!(e instanceof HttpError && e.status === 404)) return false;
        } finally {
            busyId.value = null;
        }

        items.value = items.value.filter((item) => item.id !== id);

        return true;
    }

    return { items, loading, loadingMore, error, hasMore, busyId, load, loadMore, rename, remove };
}
