<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowDownTrayIcon,
    CheckCircleIcon,
    DocumentDuplicateIcon,
    PaperAirplaneIcon,
    PencilSquareIcon,
    ReceiptPercentIcon,
    TrashIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

import Can from '@/Components/Can.vue';

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
}>();

const { t } = useI18n();

const isDraft = computed(() => props.quote.status === 'draft');
const isSent = computed(() => props.quote.status === 'sent');
const isAccepted = computed(() => props.quote.status === 'accepted');
const isItemized = computed(() => props.quote.kind === 'itemized');
const canDownloadPdf = computed(() => isItemized.value || props.quote.document !== null);
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('quote_section_actions') }}</h2>

            <Can v-if="isDraft" permission="edit quotes">
                <a :href="`/quotes/${props.quote.id}/edit`" class="btn btn-sm w-full justify-start">
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </a>
            </Can>

            <Can v-if="isDraft && isItemized" permission="send quotes">
                <button type="button" class="btn btn-sm btn-success w-full justify-start" @click="emit('send')">
                    <PaperAirplaneIcon class="size-4" />
                    {{ t('quote_action_send') }}
                </button>
            </Can>

            <Can v-if="isSent && isItemized" permission="approve quotes">
                <button type="button" class="btn btn-sm btn-success w-full justify-start" @click="emit('accept')">
                    <CheckCircleIcon class="size-4" />
                    {{ t('quote_action_accept') }}
                </button>
            </Can>

            <Can v-if="isSent && isItemized" permission="approve quotes">
                <button type="button" class="btn btn-sm w-full justify-start text-warning" @click="emit('reject')">
                    <XCircleIcon class="size-4" />
                    {{ t('quote_action_reject') }}
                </button>
            </Can>

            <Can v-if="isAccepted && isItemized" permission="create invoices">
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary w-full justify-start"
                        @click="emit('convertInvoice')"
                    >
                        <ReceiptPercentIcon class="size-4" />
                        {{ t('quote_action_convert_invoice') }}
                    </button>
                    <p v-if="props.quote.invoices.length > 0" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('quote_converted_count', { count: props.quote.invoices.length }) }}
                    </p>
                </div>
            </Can>

            <a
                v-if="canDownloadPdf"
                :href="`/quotes/${props.quote.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-sm w-full justify-start"
            >
                <ArrowDownTrayIcon class="size-4" />
                {{ t('quote_action_download_pdf') }}
            </a>

            <Can permission="create quotes">
                <button type="button" class="btn btn-sm w-full justify-start" @click="emit('duplicate')">
                    <DocumentDuplicateIcon class="size-4" />
                    {{ t('quote_action_duplicate') }}
                </button>
            </Can>

            <Can v-if="isDraft" permission="delete quotes">
                <button type="button" class="btn btn-sm w-full justify-start text-error" @click="emit('delete')">
                    <TrashIcon class="size-4" />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
