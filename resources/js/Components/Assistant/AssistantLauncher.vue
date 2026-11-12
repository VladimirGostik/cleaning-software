<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AssistantIcon from '~icons/material-symbols/smart-toy-outline-rounded';
import CloseIcon from '~icons/material-symbols/close-outline-rounded';
import AssistantPanel from '@/Components/Assistant/AssistantPanel.vue';
import { useAssistantChat } from '@/Composables/useAssistantChat';

defineProps<{ hidden?: boolean }>();

const { t } = useI18n();
const chat = useAssistantChat();

const button = ref<HTMLElement | null>(null);

watch(
    () => chat.state.open,
    async (open) => {
        if (open) return;
        await nextTick();
        button.value?.focus();
    },
);
</script>

<template>
    <button
        v-show="!hidden"
        ref="button"
        type="button"
        class="btn btn-primary btn-circle btn-lg fixed right-5 bottom-[calc(1.25rem+var(--kg-bottom-nav-clearance))] z-30"
        :aria-label="chat.state.open ? t('assistant_close') : t('assistant_open')"
        :aria-expanded="chat.state.open"
        aria-controls="assistant-panel"
        @click="chat.toggle()"
    >
        <CloseIcon v-if="chat.state.open" />
        <AssistantIcon v-else />
    </button>

    <AssistantPanel v-if="chat.state.open" />
</template>
