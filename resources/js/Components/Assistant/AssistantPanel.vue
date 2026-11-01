<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, provide, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CloseIcon from '~icons/material-symbols/close-rounded';
import SendIcon from '~icons/material-symbols/send-outline-rounded';
import NewConversationIcon from '~icons/material-symbols/add-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import TenantColorDot from '@/Components/Tenants/TenantColorDot.vue';
import AssistantMessage from '@/Components/Assistant/AssistantMessage.vue';
import { useAssistantChat } from '@/Composables/useAssistantChat';
import { usePageProps } from '@/Composables/usePageProps';
import { PENDING_ACTION_DECISION, usePendingActionDecision } from '@/Composables/usePendingActionDecision';
import { MAX_MESSAGE_CHARS } from '@/utils/assistant';

const { t } = useI18n();
const props = usePageProps();
const chat = useAssistantChat();

const decision = usePendingActionDecision({
    onSettled: (action, verb) => chat.settlePending(action.id, verb === 'confirm' ? 'done' : 'cancelled'),
});
provide(PENDING_ACTION_DECISION, decision);

const draft = ref('');
const composer = ref<HTMLTextAreaElement | null>(null);
const log = ref<HTMLElement | null>(null);
const isSendAgainOpen = ref(false);

const company = computed(() => props.value.tenant.active);
const available = computed(() => props.value.assistantAvailable);
const canChat = computed(() => available.value && company.value !== null);
const composerDisabled = computed(() => !canChat.value || chat.state.blocked);
const canSend = computed(() => !composerDisabled.value && !chat.state.sending && draft.value.trim() !== '');
const canReset = computed(() => chat.state.messages.length > 0 && !chat.state.sending);

const errorText = computed(() => {
    const error = chat.state.error;
    if (error === null) return null;
    if (error.kind === 'ai') return error.message ?? t('assistant_unavailable');
    if (error.kind === 'forbidden') return error.message ?? t('assistant_forbidden');
    if (error.kind === 'validation') return error.message ?? t('assistant_error_failed');
    if (error.kind === 'rate') return t('assistant_error_rate');
    if (error.kind === 'session') return t('assistant_error_session');
    if (error.kind === 'outcome_unknown') return t('assistant_error_outcome_unknown');
    return t('assistant_error_failed');
});

function submit(): void {
    if (!canSend.value) return;
    const text = draft.value;
    draft.value = '';
    void chat.send(text);
}

function onEnter(event: KeyboardEvent): void {
    if (event.shiftKey || event.isComposing) return;
    event.preventDefault();
    submit();
}

function checkWhatHappened(): void {
    chat.acknowledge();
    chat.closeOnSmallScreen();
}

function sendAgain(): void {
    isSendAgainOpen.value = false;
    void chat.retry();
}

watch(
    () => [chat.state.messages.length, chat.state.sending, chat.state.error],
    async () => {
        await nextTick();
        log.value?.scrollTo({ top: log.value.scrollHeight });
    },
);

onMounted(() => composer.value?.focus());
</script>

<template>
    <section
        id="assistant-panel"
        role="dialog"
        aria-modal="false"
        aria-labelledby="assistant-panel-title"
        class="fixed inset-0 z-30 flex flex-col border border-base-300 bg-base-100 shadow-float sm:inset-auto sm:right-5 sm:bottom-24 sm:h-[min(40rem,calc(100dvh-7.5rem))] sm:w-[26rem] sm:rounded-box"
        @keydown.esc="chat.close()"
    >
        <header class="flex items-center gap-2 border-b border-base-300 px-4 py-3">
            <div class="min-w-0 flex-1">
                <h2 id="assistant-panel-title" class="text-sm font-semibold">{{ t('assistant_title') }}</h2>
                <p v-if="company" class="flex items-center gap-2 text-xs text-base-content/60">
                    <TenantColorDot :color="company.color" />
                    <span class="truncate">{{ company.name }}</span>
                </p>
            </div>
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                :aria-label="t('assistant_new_conversation')"
                :title="t('assistant_new_conversation')"
                :disabled="!canReset"
                @click="chat.reset()"
            >
                <NewConversationIcon class="size-5" />
            </button>
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                :aria-label="t('assistant_close')"
                :title="t('assistant_close')"
                @click="chat.close()"
            >
                <CloseIcon class="size-5" />
            </button>
        </header>

        <div ref="log" role="log" aria-live="polite" class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
            <div v-if="!available" class="alert alert-info text-sm" role="status">
                {{ t('assistant_not_configured') }}
            </div>
            <p v-else-if="chat.state.messages.length === 0" class="py-6 text-center text-sm text-base-content/60">
                {{ t('assistant_empty_hint') }}
            </p>

            <AssistantMessage v-for="message in chat.state.messages" :key="message.id" :message="message" />

            <p v-if="chat.state.sending" class="flex items-center gap-2 text-sm text-base-content/60" role="status">
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
                <button type="button" class="btn btn-ghost btn-sm" @click="chat.retry()">
                    {{ t('retry') }}
                </button>
            </div>

            <div v-if="decision.errorMessage.value" class="alert alert-error text-sm" role="alert">
                {{ decision.errorMessage.value }}
            </div>
        </div>

        <footer class="flex items-end gap-2 border-t border-base-300 p-3">
            <textarea
                ref="composer"
                v-model="draft"
                rows="1"
                class="textarea field-sizing-content max-h-32 min-h-10 flex-1 resize-none"
                :maxlength="MAX_MESSAGE_CHARS"
                :placeholder="t('assistant_placeholder')"
                :aria-label="t('assistant_placeholder')"
                :disabled="composerDisabled"
                @keydown.enter="onEnter"
            />
            <button
                type="button"
                class="btn btn-primary btn-circle"
                :aria-label="t('assistant_send')"
                :title="t('assistant_send')"
                :disabled="!canSend"
                @click="submit"
            >
                <SendIcon class="size-5" />
            </button>
        </footer>

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
