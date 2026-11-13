<script setup lang="ts">
import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import DoneIcon from '~icons/material-symbols/check-circle-outline-rounded';
import { PENDING_ACTION_DECISION, DESTRUCTIVE_ACTIONS } from '@/Composables/usePendingActionDecision';
import type { PendingOutcome } from '@/Composables/useAssistantChat';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    action: App.Data.Ai.PendingActionData;
    outcome: PendingOutcome | null;
}>();

const { t } = useI18n();

const decision = inject(PENDING_ACTION_DECISION);
if (decision === undefined) {
    throw new Error('AssistantPendingAction needs a pending action decision provided by AssistantPanel.');
}

const destructive = computed(() => DESTRUCTIVE_ACTIONS.includes(props.action.action));
const expired = computed(
    () => props.action.status === 'expired' || new Date(props.action.expires_at).getTime() <= Date.now(),
);
const busy = computed(() => decision.processingId.value !== null);
</script>

<template>
    <div class="mt-2 rounded-card-nested bg-base-100">
        <div class="flex flex-col gap-2 p-3">
            <p class="text-sm font-medium">{{ t(`pending_action_${action.action}`) }}</p>
            <p class="whitespace-pre-wrap break-words text-sm">{{ action.preview }}</p>
            <p class="text-xs text-muted">{{ t('pending_action_expires') }}: {{ formatDatetime(action.expires_at) }}</p>

            <p v-if="outcome" class="flex items-center gap-1 text-sm text-muted">
                <DoneIcon v-if="outcome === 'done'" />
                {{ t(outcome === 'done' ? 'assistant_pending_done' : 'assistant_pending_cancelled') }}
            </p>
            <p v-else-if="expired" class="text-sm text-muted">{{ t('pending_action_expired') }}</p>
            <div v-else class="flex gap-2">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="destructive ? 'btn-error' : 'btn-primary'"
                    :disabled="busy"
                    @click="decision.requestConfirm(action)"
                >
                    {{ t('pending_action_confirm') }}
                </button>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="busy" @click="decision.cancel(action)">
                    {{ t('pending_action_cancel') }}
                </button>
            </div>
        </div>
    </div>
</template>
