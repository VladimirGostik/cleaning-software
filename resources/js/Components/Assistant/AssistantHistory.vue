<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import AssistantConversationItem from '@/Components/Assistant/AssistantConversationItem.vue';
import { useAssistantChat } from '@/Composables/useAssistantChat';
import { useAssistantConversations } from '@/Composables/useAssistantConversations';

defineProps<{ activeId: string | null }>();

const emit = defineEmits<{ opened: [] }>();

const { t } = useI18n();
const chat = useAssistantChat();
const list = useAssistantConversations();

const renamingId = ref<string | null>(null);
const renameError = ref<string | null>(null);
const toDelete = ref<App.Data.Ai.AssistantConversationItemData | null>(null);
const deleteError = ref(false);
const root = ref<HTMLElement | null>(null);

async function focusOpenButton(id: string): Promise<void> {
    await nextTick();
    root.value?.querySelector<HTMLElement>(`[data-conversation-open="${id}"]`)?.focus();
}

function open(id: string): void {
    void chat.load(id);
    emit('opened');
}

function startRename(id: string): void {
    renameError.value = null;
    renamingId.value = id;
}

function cancelRename(): void {
    const id = renamingId.value;
    renamingId.value = null;
    renameError.value = null;
    if (id !== null) void focusOpenButton(id);
}

async function rename(id: string, title: string): Promise<void> {
    const error = await list.rename(id, title);
    if (error !== null) {
        renameError.value = error;
        return;
    }

    chat.retitle(id, title);
    cancelRename();
}

function askDelete(conversation: App.Data.Ai.AssistantConversationItemData): void {
    deleteError.value = false;
    toDelete.value = conversation;
}

async function confirmDelete(): Promise<void> {
    const conversation = toDelete.value;
    if (conversation === null) return;

    if (await list.remove(conversation.id)) {
        chat.forgetIfOpen(conversation.id);
        toDelete.value = null;
        return;
    }

    deleteError.value = true;
}

onMounted(() => void list.load());
</script>

<template>
    <div ref="root" class="flex flex-col gap-2 px-4 py-2">
        <div v-if="list.loading.value" class="flex justify-center py-6" role="status">
            <span class="loading loading-dots loading-sm" aria-hidden="true" />
        </div>

        <div v-else-if="list.error.value && list.items.value.length === 0" role="alert">
            <div class="alert alert-error flex-col items-start text-sm">
                <p>{{ t('assistant_history_load_failed') }}</p>
                <button type="button" class="btn btn-ghost btn-sm" @click="list.load()">{{ t('retry') }}</button>
            </div>
        </div>

        <p v-else-if="list.items.value.length === 0" class="py-6 text-center text-sm text-muted">
            {{ t('assistant_history_empty') }}
        </p>

        <template v-else>
            <ul class="kg-list">
                <li v-for="conversation in list.items.value" :key="conversation.id">
                    <AssistantConversationItem
                        :conversation="conversation"
                        :active="conversation.id === activeId"
                        :busy="list.busyId.value === conversation.id"
                        :renaming="renamingId === conversation.id"
                        :rename-error="renamingId === conversation.id ? renameError : null"
                        @open="open"
                        @rename-start="startRename"
                        @rename-cancel="cancelRename"
                        @rename="rename"
                        @delete="askDelete"
                    />
                </li>
            </ul>
            <div v-if="list.error.value" class="text-sm text-error" role="alert">
                {{ t('assistant_history_load_failed') }}
            </div>
            <div v-if="list.hasMore.value" class="flex justify-center py-2">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    :disabled="list.loadingMore.value"
                    @click="list.loadMore()"
                >
                    {{ t('assistant_history_load_more') }}
                </button>
            </div>
        </template>

        <ConfirmDeleteModal
            :is-open="toDelete !== null"
            :title="t('assistant_conversation_delete_title')"
            :description="
                deleteError
                    ? t('assistant_conversation_delete_failed')
                    : toDelete
                      ? t('assistant_conversation_delete_description', { title: toDelete.title })
                      : ''
            "
            :confirm-label="t('assistant_conversation_delete')"
            @cancel="toDelete = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
