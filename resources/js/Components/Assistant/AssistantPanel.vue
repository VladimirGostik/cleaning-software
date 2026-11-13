<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, provide, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CloseIcon from '~icons/material-symbols/close-outline-rounded';
import NewConversationIcon from '~icons/material-symbols/add-outline-rounded';
import HistoryIcon from '~icons/material-symbols/history-outline-rounded';
import BackIcon from '~icons/material-symbols/arrow-back-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import AssistantComposer from '@/Components/Assistant/AssistantComposer.vue';
import AssistantHistory from '@/Components/Assistant/AssistantHistory.vue';
import AssistantMessage from '@/Components/Assistant/AssistantMessage.vue';
import { useAssistantAttachments } from '@/Composables/useAssistantAttachments';
import { useAssistantChat } from '@/Composables/useAssistantChat';
import { usePageProps } from '@/Composables/usePageProps';
import { PENDING_ACTION_DECISION, usePendingActionDecision } from '@/Composables/usePendingActionDecision';
import type { AssistantError } from '@/utils/assistant';

const { t } = useI18n();
const props = usePageProps();
const chat = useAssistantChat();
const attachments = useAssistantAttachments();

const decision = usePendingActionDecision({
    onSettled: (action, verb) => chat.settlePending(action.id, verb === 'confirm' ? 'done' : 'cancelled'),
});
provide(PENDING_ACTION_DECISION, decision);

const composer = useTemplateRef<InstanceType<typeof AssistantComposer>>('composer');
const log = ref<HTMLElement | null>(null);
const isSendAgainOpen = ref(false);
const returnedError = ref<string | null>(null);
const dragDepth = ref(0);
const view = ref<'chat' | 'history'>('chat');
const historyTitle = useTemplateRef<HTMLElement>('historyTitle');
const historyToggle = useTemplateRef<HTMLElement>('historyToggle');

const company = computed(() => props.value.tenant.active);
const available = computed(() => props.value.assistantAvailable);
const canChat = computed(() => available.value && company.value !== null);
const composerDisabled = computed(
    () => !canChat.value || chat.state.blocked || chat.state.loading || chat.state.loadError || view.value !== 'chat',
);
const canReset = computed(
    () => (chat.state.messages.length > 0 || chat.state.conversationId !== null) && !chat.state.sending,
);
const headerTitle = computed(() =>
    view.value === 'history' ? t('assistant_history_title') : (chat.state.title ?? t('assistant_title')),
);

function describeError(error: AssistantError | null): string | null {
    if (error === null) return null;
    if (error.kind === 'ai') return error.message ?? t('assistant_unavailable');
    if (error.kind === 'forbidden') return error.message ?? t('assistant_forbidden');
    if (error.kind === 'validation') return error.message ?? t('assistant_error_failed');
    if (error.kind === 'rate') return t('assistant_error_rate');
    if (error.kind === 'session') return t('assistant_error_session');
    if (error.kind === 'outcome_unknown') return t('assistant_error_outcome_unknown');
    if (error.kind === 'too_large') return t('assistant_error_too_large');
    if (error.kind === 'conversation_gone') return t('assistant_conversation_gone');
    return t('assistant_error_failed');
}

const errorText = computed(() => describeError(chat.state.error));
const composerError = computed(() => attachments.error.value ?? returnedError.value);

function takeBack(): void {
    const message = chat.takeReturned();
    if (message === null) return;
    composer.value?.restore(message.text);
    attachments.add(message.files);
    returnedError.value = describeError(message.error);
}

async function submit(text: string): Promise<void> {
    returnedError.value = null;
    await chat.send(text, attachments.take());
}

function retry(): void {
    void chat.retry();
}

function addFiles(files: File[]): void {
    returnedError.value = null;
    attachments.add(files);
}

function hasFiles(event: DragEvent): boolean {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function onDragEnter(event: DragEvent): void {
    if (hasFiles(event)) dragDepth.value += 1;
}

function onDragLeave(event: DragEvent): void {
    if (hasFiles(event)) dragDepth.value = Math.max(0, dragDepth.value - 1);
}

function onDragOver(event: DragEvent): void {
    if (hasFiles(event)) event.preventDefault();
}

function onDrop(event: DragEvent): void {
    if (!hasFiles(event)) return;
    event.preventDefault();
    dragDepth.value = 0;
    if (!composerDisabled.value) addFiles(Array.from(event.dataTransfer?.files ?? []));
}

function checkWhatHappened(): void {
    chat.acknowledge();
    chat.closeOnSmallScreen();
}

function sendAgain(): void {
    isSendAgainOpen.value = false;
    void retry();
}

async function showHistory(): Promise<void> {
    view.value = 'history';
    await nextTick();
    historyTitle.value?.focus();
}

async function showChat(): Promise<void> {
    view.value = 'chat';
    await nextTick();
    historyToggle.value?.focus();
}

function startNew(): void {
    chat.startNew();
    view.value = 'chat';
}

function retryLoad(): void {
    const id = chat.state.conversationId;
    if (id !== null) void chat.load(id);
}

watch(chat.returned, takeBack);
onMounted(() => {
    takeBack();
    void chat.restoreRemembered();
});

watch(
    () => [chat.state.messages.length, chat.state.sending, chat.state.error],
    async () => {
        await nextTick();
        log.value?.scrollTo({ top: log.value.scrollHeight });
    },
);
</script>

<template>
    <section
        id="assistant-panel"
        role="dialog"
        aria-modal="false"
        aria-labelledby="assistant-panel-title"
        class="kg-float kg-float--panel fixed inset-0 z-30 flex flex-col sm:inset-auto sm:right-5 sm:bottom-[calc(1.25rem+var(--kg-bottom-nav-clearance)+3.375rem+0.75rem)] sm:h-[min(48rem,calc(100dvh-7.5rem-var(--kg-bottom-nav-clearance)))] sm:w-[min(36rem,calc(100vw-2.5rem))]"
        @keydown.esc="chat.close()"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
    >
        <div
            v-if="dragDepth > 0 && !composerDisabled"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-[inherit] bg-base-100 text-sm font-medium text-primary"
            aria-hidden="true"
        >
            {{ t('assistant_drop_files') }}
        </div>

        <header class="flex items-center gap-2 px-4 py-3">
            <button
                v-if="view === 'history'"
                type="button"
                class="btn btn-ghost btn-square"
                :aria-label="t('assistant_history_back')"
                :title="t('assistant_history_back')"
                @click="showChat"
            >
                <BackIcon />
            </button>
            <div class="min-w-0 flex-1">
                <h2
                    id="assistant-panel-title"
                    ref="historyTitle"
                    tabindex="-1"
                    class="truncate text-lg font-medium outline-none"
                >
                    {{ headerTitle }}
                </h2>
                <p v-if="view === 'chat'" class="text-xs text-muted">{{ t('assistant_all_companies') }}</p>
            </div>
            <button
                v-if="view === 'chat'"
                ref="historyToggle"
                type="button"
                class="btn btn-ghost btn-square"
                :aria-label="t('assistant_history_open')"
                :title="t('assistant_history_open')"
                :disabled="chat.state.sending"
                @click="showHistory"
            >
                <HistoryIcon />
            </button>
            <button
                type="button"
                class="btn btn-ghost btn-square"
                :aria-label="t('assistant_new_conversation')"
                :title="t('assistant_new_conversation')"
                :disabled="!canReset"
                @click="startNew"
            >
                <NewConversationIcon />
            </button>
            <button
                type="button"
                class="btn btn-ghost btn-square"
                :aria-label="t('assistant_close')"
                :title="t('assistant_close')"
                @click="chat.close()"
            >
                <CloseIcon />
            </button>
        </header>

        <div v-if="view === 'history'" class="flex-1 overflow-y-auto">
            <AssistantHistory :active-id="chat.state.conversationId" @opened="view = 'chat'" />
        </div>

        <div v-else ref="log" role="log" aria-live="polite" class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
            <div v-if="!available" class="alert alert-info text-sm" role="status">
                {{ t('assistant_not_configured') }}
            </div>
            <p
                v-else-if="chat.state.messages.length === 0 && !chat.state.loading && !chat.state.loadError"
                class="py-6 text-center text-sm text-muted"
            >
                {{ t('assistant_empty_hint') }}
            </p>

            <p v-if="chat.state.loading" class="flex justify-center py-6" role="status">
                <span class="loading loading-dots loading-sm" aria-hidden="true" />
            </p>
            <div v-if="chat.state.loadError" class="alert alert-error flex-col items-start text-sm" role="alert">
                <p>{{ t('assistant_conversation_load_failed') }}</p>
                <button
                    v-if="chat.state.conversationId !== null"
                    type="button"
                    class="btn btn-ghost btn-sm"
                    @click="retryLoad"
                >
                    {{ t('retry') }}
                </button>
            </div>

            <AssistantMessage v-for="message in chat.state.messages" :key="message.id" :message="message" />

            <p v-if="chat.state.sending" class="flex items-center gap-2 text-sm text-muted" role="status">
                <span class="loading loading-dots loading-sm" aria-hidden="true" />
                {{ t('assistant_thinking') }}
            </p>

            <div v-if="chat.state.blocked" class="alert alert-warning flex-col items-start text-sm" role="alert">
                <p>{{ errorText }}</p>
                <p>{{ t('assistant_blocked') }}</p>
                <div class="flex flex-wrap gap-2">
                    <Link href="/pending-actions" class="btn btn-neutral btn-sm" @click="checkWhatHappened">
                        {{ t('assistant_check_what_happened') }}
                    </Link>
                    <button type="button" class="btn btn-ghost btn-sm" @click="isSendAgainOpen = true">
                        {{ t('assistant_send_again') }}
                    </button>
                </div>
            </div>
            <div v-else-if="errorText" class="alert alert-error flex-col items-start text-sm" role="alert">
                <p>{{ errorText }}</p>
                <button type="button" class="btn btn-ghost btn-sm" @click="retry">
                    {{ t('retry') }}
                </button>
            </div>

            <div v-if="decision.errorMessage.value" class="alert alert-error text-sm" role="alert">
                {{ decision.errorMessage.value }}
            </div>
        </div>

        <AssistantComposer
            v-show="view === 'chat'"
            ref="composer"
            :items="attachments.items.value"
            :error="composerError"
            :disabled="composerDisabled"
            :sending="chat.state.sending"
            :is-full="attachments.isFull.value"
            @add="addFiles"
            @remove="attachments.remove"
            @submit="submit"
        />

        <ConfirmDeleteModal
            :is-open="decision.toConfirm.value !== null"
            :title="t('pending_action_confirm_title')"
            :description="
                decision.toConfirm.value
                    ? t('pending_action_confirm_description', { preview: decision.toConfirm.value.preview })
                    : ''
            "
            :confirm-label="t('pending_action_confirm')"
            @cancel="decision.dismissModal()"
            @confirm="decision.confirmModal()"
        />

        <ConfirmDeleteModal
            :is-open="isSendAgainOpen"
            :title="t('assistant_send_again')"
            :description="t('assistant_send_again_confirm')"
            :confirm-label="t('assistant_send_again')"
            confirm-variant="warning"
            @cancel="isSendAgainOpen = false"
            @confirm="sendAgain"
        />
    </section>
</template>
