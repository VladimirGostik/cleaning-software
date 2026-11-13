<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MoreIcon from '~icons/material-symbols/more-horiz-outline-rounded';
import EditIcon from '~icons/material-symbols/edit-outline-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import AssistantConversationRename from '@/Components/Assistant/AssistantConversationRename.vue';
import { formatShortDatetime } from '@/utils/date';

const props = defineProps<{
    conversation: App.Data.Ai.AssistantConversationItemData;
    active: boolean;
    busy: boolean;
    renaming: boolean;
    renameError: string | null;
}>();

const emit = defineEmits<{
    open: [id: string];
    'rename-start': [id: string];
    'rename-cancel': [];
    rename: [id: string, title: string];
    delete: [conversation: App.Data.Ai.AssistantConversationItemData];
}>();

const { t } = useI18n();
const menuOpen = ref(false);

function startRename(): void {
    menuOpen.value = false;
    emit('rename-start', props.conversation.id);
}

function askDelete(): void {
    menuOpen.value = false;
    emit('delete', props.conversation);
}
</script>

<template>
    <AssistantConversationRename
        v-if="renaming"
        :initial="conversation.title"
        :error="renameError"
        :saving="busy"
        @save="(title) => emit('rename', conversation.id, title)"
        @cancel="emit('rename-cancel')"
    />
    <div v-else class="kg-list-row">
        <button
            type="button"
            class="min-w-0 flex-1 text-left"
            :data-conversation-open="conversation.id"
            :aria-current="active ? 'true' : undefined"
            :disabled="busy"
            @click="emit('open', conversation.id)"
        >
            <span class="block truncate text-base">{{ conversation.title }}</span>
            <span class="block text-xs text-muted">{{ formatShortDatetime(conversation.last_message_at) }}</span>
        </button>
        <AnchoredPopover v-model:open="menuOpen">
            <template #trigger="{ toggle }">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square"
                    aria-haspopup="menu"
                    :aria-expanded="menuOpen"
                    :aria-label="t('assistant_conversation_menu', { title: conversation.title })"
                    :disabled="busy"
                    @click="toggle"
                >
                    <MoreIcon />
                </button>
            </template>
            <ul class="menu" role="menu">
                <li role="none">
                    <button type="button" role="menuitem" @click="startRename">
                        <EditIcon />
                        {{ t('assistant_conversation_rename') }}
                    </button>
                </li>
                <li role="none">
                    <button type="button" role="menuitem" @click="askDelete">
                        <DeleteIcon />
                        {{ t('assistant_conversation_delete') }}
                    </button>
                </li>
            </ul>
        </AnchoredPopover>
    </div>
</template>
