<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ArrowDownTrayIcon,
    BanknotesIcon,
    CheckBadgeIcon,
    DocumentDuplicateIcon,
    EnvelopeIcon,
    PencilSquareIcon,
    TrashIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

import Can from '@/Components/Can.vue';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const emit = defineEmits<{
    issue: [];
    pay: [];
    cancel: [];
    send: [];
    duplicate: [];
    delete: [];
}>();

const { t } = useI18n();

const isDraft = computed(() => props.invoice.status === 'draft');
const isIssuedOrOverdue = computed(() => props.invoice.status === 'issued' || props.invoice.status === 'overdue');
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('invoice_section_actions') }}</h2>

            <Can v-if="isDraft" permission="edit invoices">
                <a :href="`/invoices/${props.invoice.id}/edit`" class="btn btn-sm w-full justify-start">
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </a>
            </Can>

            <Can v-if="isDraft" permission="edit invoices">
                <button type="button" class="btn btn-sm btn-success w-full justify-start" @click="emit('issue')">
                    <CheckBadgeIcon class="size-4" />
                    {{ t('invoice_action_issue') }}
                </button>
            </Can>

            <Can v-if="isIssuedOrOverdue" permission="edit invoices">
                <button type="button" class="btn btn-sm w-full justify-start" @click="emit('pay')">
                    <BanknotesIcon class="size-4" />
                    {{ t('invoice_action_mark_paid') }}
                </button>
            </Can>

            <a
                :href="`/invoices/${props.invoice.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-sm w-full justify-start"
            >
                <ArrowDownTrayIcon class="size-4" />
                {{ t('invoice_action_download_pdf') }}
            </a>

            <Can v-if="props.invoice.status === 'issued'" permission="edit invoices">
                <div>
                    <button
                        type="button"
                        class="btn btn-sm w-full justify-start"
                        :disabled="!props.invoice.customer_email"
                        :title="!props.invoice.customer_email ? t('invoice_send_requires_email') : undefined"
                        @click="emit('send')"
                    >
                        <EnvelopeIcon class="size-4" />
                        {{ t('invoice_action_send_email') }}
                    </button>
                    <p v-if="!props.invoice.customer_email" class="mt-1 pl-1 text-xs text-base-content/50">
                        {{ t('invoice_send_requires_email') }}
                    </p>
                </div>
            </Can>

            <Can permission="create invoices">
                <button type="button" class="btn btn-sm w-full justify-start" @click="emit('duplicate')">
                    <DocumentDuplicateIcon class="size-4" />
                    {{ t('invoice_action_duplicate') }}
                </button>
            </Can>

            <Can v-if="isIssuedOrOverdue" permission="cancel invoices">
                <button type="button" class="btn btn-sm w-full justify-start text-warning" @click="emit('cancel')">
                    <XCircleIcon class="size-4" />
                    {{ t('invoice_action_cancel') }}
                </button>
            </Can>

            <Can v-if="isDraft" permission="cancel invoices">
                <button type="button" class="btn btn-sm w-full justify-start text-error" @click="emit('delete')">
                    <TrashIcon class="size-4" />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
