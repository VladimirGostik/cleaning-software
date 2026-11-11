<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import DocumentCheckIcon from '~icons/material-symbols/task-outline-rounded';
import DocumentDuplicateIcon from '~icons/material-symbols/content-copy-outline-rounded';
import PaperAirplaneIcon from '~icons/material-symbols/send-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import ReceiptPercentIcon from '~icons/material-symbols/request-quote-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import XCircleIcon from '~icons/material-symbols/cancel-outline-rounded';

import Can from '@/Components/Can.vue';
import QuoteRecurringInvoiceAction from './QuoteRecurringInvoiceAction.vue';
import { isRecurringPeriod } from '@/utils/quoteBilling';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const emit = defineEmits<{
    send: [];
    accept: [];
    reject: [];
    duplicate: [];
    delete: [];
    convertInvoice: [];
    convertContract: [];
}>();

const { t } = useI18n();

const isDraft = computed(() => props.quote.status === 'draft');
const isSent = computed(() => props.quote.status === 'sent');
const isAccepted = computed(() => props.quote.status === 'accepted');
const isItemized = computed(() => props.quote.kind === 'itemized');
const canDownloadPdf = computed(() => isItemized.value || props.quote.document !== null);
const hasMissingTokens = computed(() => (props.quote.text_document?.missing_tokens.length ?? 0) > 0);
const hasOneOffGroup = computed(() => props.quote.period_totals.some((group) => group.billing_period === 'one_off'));
const recurringPeriods = computed(() =>
    props.quote.period_totals.map((group) => group.billing_period).filter(isRecurringPeriod),
);
const canConvertContract = computed(() => props.quote.client_id !== null && props.quote.cleaning_object_id !== null);
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('quote_section_actions') }}</h2>

            <Can v-if="props.quote.is_editable" permission="edit quotes">
                <Link :href="`/quotes/${props.quote.id}/edit`" class="btn btn-sm btn-neutral w-full justify-start">
                    <PencilSquareIcon class="size-5" />
                    {{ t('edit') }}
                </Link>
            </Can>

            <Can v-if="isDraft && isItemized" permission="send quotes">
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-secondary w-full justify-start"
                        :disabled="hasMissingTokens"
                        @click="emit('send')"
                    >
                        <PaperAirplaneIcon class="size-5" />
                        {{ t('quote_action_send') }}
                    </button>
                    <p v-if="hasMissingTokens" class="mt-1 pl-1 text-xs text-warning">
                        {{ t('quote_send_blocked_missing_tokens') }}
                    </p>
                </div>
            </Can>

            <Can v-if="isSent && isItemized" permission="approve quotes">
                <button type="button" class="btn btn-sm btn-primary w-full justify-start" @click="emit('accept')">
                    <CheckCircleIcon class="size-5" />
                    {{ t('quote_action_accept') }}
                </button>
            </Can>

            <Can v-if="isSent && isItemized" permission="approve quotes">
                <button type="button" class="btn btn-sm btn-neutral w-full justify-start" @click="emit('reject')">
                    <XCircleIcon class="size-5" />
                    {{ t('quote_action_reject') }}
                </button>
            </Can>

            <Can v-if="isAccepted && isItemized && hasOneOffGroup" permission="create invoices">
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary w-full justify-start"
                        @click="emit('convertInvoice')"
                    >
                        <ReceiptPercentIcon class="size-5" />
                        {{ t('quote_action_convert_invoice') }}
                    </button>
                    <p v-if="recurringPeriods.length > 0" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('quote_convert_invoice_one_off_only') }}
                    </p>
                    <p v-if="props.quote.invoices.length > 0" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('quote_converted_count', { count: props.quote.invoices.length }) }}
                    </p>
                </div>
            </Can>

            <Can v-if="isAccepted && isItemized && recurringPeriods.length > 0" permission="create recurring_invoices">
                <QuoteRecurringInvoiceAction :quote-id="props.quote.id" :periods="recurringPeriods" />
            </Can>

            <Can v-if="isAccepted && isItemized" permission="create contracts">
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary w-full justify-start"
                        :disabled="!canConvertContract"
                        :title="!canConvertContract ? t('quote_convert_contract_requires_object') : undefined"
                        @click="emit('convertContract')"
                    >
                        <DocumentCheckIcon class="size-5" />
                        {{ t('quote_action_convert_contract') }}
                    </button>
                    <p v-if="!canConvertContract" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('quote_convert_contract_requires_object') }}
                    </p>
                    <p v-if="props.quote.contracts.length > 0" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('quote_converted_contracts_count', { count: props.quote.contracts.length }) }}
                    </p>
                </div>
            </Can>

            <a
                v-if="canDownloadPdf"
                :href="`/quotes/${props.quote.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-neutral w-full justify-start"
            >
                <ArrowDownTrayIcon class="size-5" />
                {{ t('quote_action_download_pdf') }}
            </a>

            <Can permission="create quotes">
                <button type="button" class="btn btn-sm btn-neutral w-full justify-start" @click="emit('duplicate')">
                    <DocumentDuplicateIcon class="size-5" />
                    {{ t('quote_action_duplicate') }}
                </button>
            </Can>

            <Can v-if="isDraft" permission="delete quotes">
                <button type="button" class="btn btn-sm btn-error w-full justify-start" @click="emit('delete')">
                    <TrashIcon class="size-5" />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
