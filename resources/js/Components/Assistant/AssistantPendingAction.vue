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
const expired = computed(() => new Date(props.action.expires_at).getTime() <= Date.now());
const busy = computed(() => decision.processingId.value !== null);
</script>

<template>
    <div class="card card-border mt-2 bg-base-100">
        <div class="card-body gap-2 p-3">
            <p class="text-sm font-medium">{{ t(`pending_action_${action.action}`) }}</p>
            <p class="whitespace-pre-wrap break-words text-sm">{{ action.preview }}</p>
            <p class="text-xs text-base-content/60">
                {{ t('pending_action_expires') }}: {{ formatDatetime(action.expires_at) }}
            </p>

            <p v-if="outcome" class="flex items-center gap-1 text-sm text-base-content/70">
                <DoneIcon v-if="outcome === 'done'" class="size-4" />
                {{ t(outcome === 'done' ? 'assistant_pending_done' : 'assistant_pending_cancelled') }}
            </p>
            <p v-else-if="expired" class="text-sm text-base-content/70">{{ t('pending_action_expired') }}</p>
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
