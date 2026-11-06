<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import type { BulkPdfError, BulkPdfPhase } from '@/Composables/useInvoiceBulkPdf';

const props = defineProps<{
    phase: BulkPdfPhase;
    done: number;
    total: number;
    error: BulkPdfError | null;
}>();

const emit = defineEmits<{
    cancel: [];
    dismiss: [];
}>();

const { t } = useI18n();

const label = computed(() => {
    if (props.phase === 'collecting') return t('invoice_bulk_pdf_collecting');
    if (props.phase === 'merging') return t('invoice_bulk_pdf_merging');
    return t('invoice_bulk_pdf_progress', { done: props.done, total: props.total });
});
</script>

<template>
    <div v-if="phase === 'error' && error" role="alert" class="alert alert-error mb-3 text-sm">
        <span>{{ t(error) }}</span>
        <button type="button" class="btn btn-ghost btn-sm" @click="emit('dismiss')">{{ t('close') }}</button>
    </div>
    <div
        v-else-if="phase !== 'idle'"
        role="status"
        aria-live="polite"
        class="mb-3 flex flex-wrap items-center gap-3 rounded-box border border-base-300 bg-base-200 p-3 text-sm"
    >
        <span class="loading loading-spinner loading-sm" aria-hidden="true" />
        <span>{{ label }}</span>
        <progress v-if="phase === 'downloading'" class="progress progress-primary w-40" :value="done" :max="total" />
        <button type="button" class="btn btn-neutral btn-sm ml-auto" @click="emit('cancel')">
            {{ t('cancel') }}
        </button>
    </div>
</template>
