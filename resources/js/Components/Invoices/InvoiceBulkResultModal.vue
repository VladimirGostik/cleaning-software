<script setup lang="ts">
import { useI18n } from 'vue-i18n';

defineProps<{
    open: boolean;
    result: App.Data.Invoices.BulkActionResultData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': open }">
        <div v-if="result" class="modal-box max-w-2xl">
            <h3 class="text-lg font-bold">{{ t('invoice_bulk_result_title') }}</h3>

            <div class="mt-3 flex flex-wrap gap-2">
                <span class="badge badge-success">{{
                    t('invoice_bulk_result_processed', { count: result.processed })
                }}</span>
                <span v-if="result.skipped.length" class="badge badge-warning">
                    {{ t('invoice_bulk_result_skipped', { count: result.skipped.length }) }}
                </span>
            </div>

            <div v-if="result.skipped.length" class="mt-4 max-h-80 overflow-y-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ t('invoice_bulk_result_invoice') }}</th>
                            <th>{{ t('invoice_bulk_result_reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in result.skipped" :key="item.id">
                            <td class="font-mono">{{ item.number ?? t('invoice_draft_number') }}</td>
                            <td>{{ t(`invoice_bulk_skip_${item.reason}`) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-primary" @click="emit('close')">{{ t('close') }}</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">close</button>
        </form>
    </dialog>
</template>
