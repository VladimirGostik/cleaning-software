<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useInvoiceOptionSearch } from '@/Composables/useInvoiceOptionSearch';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import type { PaymentImportRow } from '@/utils/invoicePaymentImport';

const props = defineProps<{
    open: boolean;
    row: PaymentImportRow | null;
}>();

const emit = defineEmits<{
    close: [];
    select: [invoice: App.Data.Invoices.PaymentImportInvoiceData];
    clear: [];
}>();

const { t, locale } = useI18n();
const titleId = useId();
const box = ref<HTMLElement | null>(null);
const finder = useInvoiceOptionSearch();
let trigger: HTMLElement | null = null;

watch(
    () => props.open,
    async (open) => {
        if (open && props.row) {
            trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            finder.start(props.row.amount);
            await nextTick();
            box.value?.querySelector('input')?.focus();
        } else {
            finder.stop();
            trigger?.focus();
            trigger = null;
        }
    },
);

function isExact(invoice: App.Data.Invoices.PaymentImportInvoiceData): boolean {
    return props.row !== null && Number(invoice.balance_due) === Number(props.row.amount);
}
</script>

<template>
    <dialog
        class="modal modal-bottom sm:modal-middle"
        :class="{ 'modal-open': open }"
        :aria-labelledby="titleId"
        aria-modal="true"
        @keydown.esc="emit('close')"
    >
        <div v-if="open && row" ref="box" class="modal-box max-w-2xl">
            <h3 :id="titleId" class="text-lg font-medium">{{ t('invoice_payment_import_assign_title') }}</h3>
            <p class="mt-1 text-sm text-muted tabular-nums">
                {{ formatMoney(row.amount, 'EUR', locale) }} · {{ row.payer_name ?? t('empty_dash') }}
                <template v-if="row.variable_symbol"> · {{ row.variable_symbol }}</template>
            </p>

            <TextInput
                v-model="finder.search.value"
                type="search"
                class="mt-3"
                :label="t('invoice_payment_import_assign_search')"
            />

            <p v-if="finder.error.value" role="alert" class="text-sm text-error">{{ finder.error.value }}</p>
            <div v-else-if="finder.loading.value" class="py-4 text-center">
                <span class="loading loading-spinner loading-sm" />
            </div>
            <p v-else-if="finder.results.value.length === 0" class="py-4 text-center text-sm text-muted">
                {{ t('invoice_payment_import_assign_no_results') }}
            </p>
            <ul v-else class="mt-2 max-h-80 space-y-1 overflow-y-auto">
                <li v-for="invoice in finder.results.value" :key="invoice.id">
                    <button
                        type="button"
                        class="w-full rounded-tile bg-base-200 p-3 text-left text-sm hover:bg-neutral"
                        @click="emit('select', invoice)"
                    >
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium tabular-nums">{{ invoice.number }}</span>
                            <span v-if="isExact(invoice)" class="text-xs font-medium text-success">{{
                                t('invoice_payment_import_assign_exact')
                            }}</span>
                        </span>
                        <span v-if="invoice.customer_name" class="block text-muted">{{ invoice.customer_name }}</span>
                        <span class="block text-muted tabular-nums">
                            {{ formatDate(invoice.due_date) }} · {{ formatMoney(invoice.total, 'EUR', locale) }} ·
                            {{ t('invoice_payment_import_balance') }}
                            {{ formatMoney(invoice.balance_due, 'EUR', locale) }}
                        </span>
                    </button>
                </li>
            </ul>

            <div class="modal-action">
                <button v-if="row.manual_invoice" type="button" class="btn btn-neutral" @click="emit('clear')">
                    {{ t('invoice_payment_import_unassign') }}
                </button>
                <button type="button" class="btn btn-neutral" @click="emit('close')">
                    {{ t('invoice_payment_import_assign_close') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button
                type="button"
                tabindex="-1"
                :aria-label="t('invoice_payment_import_assign_close')"
                @click="emit('close')"
            />
        </form>
    </dialog>
</template>
