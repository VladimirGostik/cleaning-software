<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatDatetime } from '@/utils/date';

defineProps<{
    action: App.Data.Ai.PendingActionData;
    processing: boolean;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

const { t } = useI18n();
</script>

<template>
    <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 space-y-1">
            <p class="font-medium">{{ t(`pending_action_${action.action}`) }}</p>
            <p class="whitespace-pre-wrap break-words text-sm">{{ action.preview }}</p>
            <p class="text-xs text-base-content/60">
                {{ t('pending_action_company') }}: {{ action.tenant_name }} · {{ t('pending_action_expires') }}:
                {{ formatDatetime(action.expires_at) }}
            </p>
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="button" class="btn btn-primary btn-sm" :disabled="processing" @click="emit('confirm')">
                {{ t('pending_action_confirm') }}
            </button>
            <button type="button" class="btn btn-ghost btn-sm" :disabled="processing" @click="emit('cancel')">
                {{ t('pending_action_cancel') }}
            </button>
        </div>
    </li>
</template>
