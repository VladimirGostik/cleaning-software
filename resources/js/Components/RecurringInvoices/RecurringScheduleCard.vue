<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { CheckIcon } from '@heroicons/vue/24/outline';
import { formatDate, formatDatetime } from '@/utils/date';
import { recurringFrequencyKey } from '@/utils/enums';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-base">{{ t('recurring_invoice_section_schedule') }}</h2>

            <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_frequency') }}</p>
                    <p>{{ t(recurringFrequencyKey(props.recurringInvoice.frequency)) }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_day_of_month') }}</p>
                    <p>{{ props.recurringInvoice.day_of_month }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_next_run') }}</p>
                    <p>{{ formatDate(props.recurringInvoice.next_run_at) }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_occurrences') }}</p>
                    <p>
                        {{ props.recurringInvoice.occurrences_generated }}/{{
                            props.recurringInvoice.occurrences_limit ?? '∞'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_auto_issue') }}</p>
                    <CheckIcon v-if="props.recurringInvoice.auto_issue" class="size-4 text-success" />
                    <p v-else>{{ t('empty_dash') }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_due_days') }}</p>
                    <p>{{ props.recurringInvoice.due_days }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_start_date') }}</p>
                    <p>{{ formatDate(props.recurringInvoice.start_date) }}</p>
                </div>
                <div v-if="props.recurringInvoice.end_date">
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_end_date') }}</p>
                    <p>{{ formatDate(props.recurringInvoice.end_date) }}</p>
                </div>
                <div v-if="props.recurringInvoice.last_generated_at">
                    <p class="text-xs text-base-content/50">{{ t('recurring_invoice_last_generated_at') }}</p>
                    <p>{{ formatDatetime(props.recurringInvoice.last_generated_at) }}</p>
                </div>
                <div v-if="props.recurringInvoice.period_from" class="col-span-2 sm:col-span-3">
                    <p class="text-xs text-base-content/50">{{ t('invoice_pdf_period') }}</p>
                    <p>
                        {{ formatDate(props.recurringInvoice.period_from) }} –
                        {{ formatDate(props.recurringInvoice.period_to) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
