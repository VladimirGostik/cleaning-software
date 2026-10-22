<script setup lang="ts">
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import InvoiceBulkPayModal from '@/Components/Invoices/InvoiceBulkPayModal.vue';
import { useAuthorization } from '@/Composables/useAuthorization';

const props = defineProps<{
    ids: string[];
    limits: App.Data.Invoices.InvoiceBulkLimitsData;
}>();

const emit = defineEmits<{
    clear: [];
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const ui = reactive({
    pay: false,
    confirm: null as 'send' | 'issue' | null,
    processing: false,
    error: null as string | null,
});

const pdfTooMany = computed(() => props.ids.length > props.limits.pdf_max_ids);
const tooMany = computed(() => props.ids.length > props.limits.max_ids);
const pdfHref = computed(() => {
    const params = new URLSearchParams();
    props.ids.forEach((id) => params.append('ids[]', id));
    return `/invoices/bulk/pdf?${params.toString()}`;
});

function runConfirmed(): void {
    const action = ui.confirm;
    if (!action) return;
    ui.processing = true;
    ui.error = null;
    router.post(
        `/invoices/bulk/${action}`,
        { ids: props.ids },
        {
            preserveScroll: true,
            onSuccess: () => emit('clear'),
            onError: (errors: Record<string, string>) => {
                ui.error = errors.ids ?? Object.values(errors)[0] ?? null;
            },
            onFinish: () => {
                ui.processing = false;
                ui.confirm = null;
            },
        },
    );
}
</script>

<template>
    <div
        class="sticky top-0 z-20 mb-3 flex flex-wrap items-center gap-2 rounded-box border border-base-300 bg-base-200 p-2"
    >
        <span class="px-2 text-sm font-medium">{{ t('invoice_bulk_selected', { count: ids.length }) }}</span>
        <button type="button" class="btn btn-ghost btn-sm" @click="emit('clear')">
            {{ t('invoice_bulk_clear_selection') }}
        </button>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <template v-if="allows('view invoices')">
                <span
                    v-if="pdfTooMany"
                    class="tooltip"
                    :data-tip="t('invoice_bulk_pdf_limit', { max: limits.pdf_max_ids })"
                >
                    <button type="button" class="btn btn-sm" disabled>{{ t('invoice_bulk_download_pdf') }}</button>
                </span>
                <a v-else :href="pdfHref" target="_blank" rel="noopener" class="btn btn-sm">
                    {{ t('invoice_bulk_download_pdf') }}
                </a>
            </template>

            <template v-if="allows('edit invoices')">
                <span class="tooltip" :data-tip="tooMany ? t('invoice_bulk_max_ids', { max: limits.max_ids }) : null">
                    <button type="button" class="btn btn-sm" :disabled="tooMany" @click="ui.confirm = 'send'">
                        {{ t('invoice_bulk_send') }}
                    </button>
                </span>
                <span class="tooltip" :data-tip="tooMany ? t('invoice_bulk_max_ids', { max: limits.max_ids }) : null">
                    <button type="button" class="btn btn-sm" :disabled="tooMany" @click="ui.pay = true">
                        {{ t('invoice_bulk_pay') }}
                    </button>
                </span>
                <span class="tooltip" :data-tip="tooMany ? t('invoice_bulk_max_ids', { max: limits.max_ids }) : null">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="tooMany"
                        @click="ui.confirm = 'issue'"
                    >
                        {{ t('invoice_bulk_issue') }}
                    </button>
                </span>
            </template>
        </div>
    </div>

    <div v-if="ui.error" role="alert" class="alert alert-error mb-3 text-sm">{{ ui.error }}</div>

    <InvoiceBulkPayModal :open="ui.pay" :ids="ids" @close="ui.pay = false" @done="emit('clear')" />

    <ConfirmDeleteModal
        :is-open="ui.confirm !== null"
        :title="ui.confirm === 'issue' ? t('invoice_bulk_issue_title') : t('invoice_bulk_send_title')"
        :description="
            ui.confirm === 'issue'
                ? t('invoice_bulk_issue_message', { count: ids.length })
                : t('invoice_bulk_send_message', { count: ids.length })
        "
        :confirm-label="ui.confirm === 'issue' ? t('invoice_bulk_issue') : t('invoice_bulk_send')"
        :confirm-variant="ui.confirm === 'issue' ? 'success' : 'primary'"
        @cancel="ui.confirm = null"
        @confirm="runConfirmed"
    />
</template>
