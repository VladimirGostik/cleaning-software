<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { endOfMonth, format, startOfMonth, subMonths } from 'date-fns';

import DateInput from '@/Components/Forms/DateInput.vue';
import { usePohodaPreview } from '@/Composables/usePohodaPreview';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const page = usePage();

const DATE_FORMAT = 'yyyy-MM-dd';
const previousMonth = subMonths(new Date(), 1);

const from = ref<string | null>(format(startOfMonth(previousMonth), DATE_FORMAT));
const to = ref<string | null>(format(endOfMonth(previousMonth), DATE_FORMAT));
const open = computed(() => props.open);

const { preview, loading, failed, fieldErrors, canDownload, retry } = usePohodaPreview(from, to, open);

const downloadErrors = ref<{ from: string | null; to: string | null }>({ from: null, to: null });

const fromError = computed(() => fieldErrors.value.from ?? downloadErrors.value.from);
const toError = computed(() => fieldErrors.value.to ?? downloadErrors.value.to);

watch([from, to], () => {
    downloadErrors.value = { from: null, to: null };
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) downloadErrors.value = { from: null, to: null };
    },
);

function download(): void {
    if (!canDownload.value || !from.value || !to.value) return;
    const query = new URLSearchParams({ from: from.value, to: to.value });
    window.location.href = `/invoices/export/pohoda?${query.toString()}`;
    emit('close');
}

watch(
    () => page.props.errors as Record<string, string> | undefined,
    (errors) => {
        if (!errors) return;
        downloadErrors.value = { from: errors.from ?? null, to: errors.to ?? null };
    },
);
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ t('pohoda_export_title') }}</h3>
            <p class="mt-1 text-sm text-base-content/60">{{ t('pohoda_export_hint') }}</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <DateInput v-model="from" :label="t('pohoda_export_from')" :error="fromError" />
                <DateInput v-model="to" :label="t('pohoda_export_to')" :error="toError" />
            </div>

            <div class="mt-4 min-h-12 text-sm" aria-live="polite">
                <p v-if="loading" class="flex items-center gap-2 text-base-content/60">
                    <span class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                    {{ t('pohoda_export_loading') }}
                </p>
                <div v-else-if="failed" class="alert alert-error alert-soft" role="alert">
                    <span>{{ t('pohoda_export_preview_failed') }}</span>
                    <button type="button" class="btn btn-ghost btn-xs" @click="retry">{{ t('retry') }}</button>
                </div>
                <template v-else-if="preview">
                    <p v-if="preview.count === 0">{{ t('pohoda_export_empty') }}</p>
                    <p v-else class="font-medium">{{ t('pohoda_export_count', { count: preview.count }) }}</p>
                    <p v-if="preview.skipped_non_eur > 0" class="mt-1 text-warning">
                        {{ t('pohoda_export_skipped_non_eur', { count: preview.skipped_non_eur }) }}
                    </p>
                </template>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" @click="emit('close')">{{ t('cancel') }}</button>
                <button type="button" class="btn btn-primary" :disabled="!canDownload" @click="download">
                    {{ t('pohoda_export_download') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">close</button>
        </form>
    </dialog>
</template>
