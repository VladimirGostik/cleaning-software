<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import BanknotesIcon from '~icons/material-symbols/receipt-long-outline-rounded';
import CheckBadgeIcon from '~icons/material-symbols/verified-outline-rounded';
import DocumentDuplicateIcon from '~icons/material-symbols/content-copy-outline-rounded';
import EnvelopeIcon from '~icons/material-symbols/mail-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import XCircleIcon from '~icons/material-symbols/cancel-outline-rounded';

import Can from '@/Components/Can.vue';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
    recipientChoosable: boolean;
}>();

const emit = defineEmits<{
    issue: [];
    pay: [];
    cancel: [];
    send: [];
    duplicate: [];
    delete: [];
    preview: [];
}>();

const { t } = useI18n();

const canSend = computed(() => !!props.invoice.customer_email || props.recipientChoosable);
const isDraft = computed(() => props.invoice.status === 'draft');
const isIssuedOrOverdue = computed(() => props.invoice.status === 'issued' || props.invoice.status === 'overdue');
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-2">
            <h2 class="card-title">{{ t('invoice_section_actions') }}</h2>

            <Can v-if="isDraft" permission="edit invoices">
                <Link :href="`/invoices/${props.invoice.id}/edit`" class="btn btn-neutral w-full justify-start">
                    <PencilSquareIcon />
                    {{ t('edit') }}
                </Link>
            </Can>

            <Can v-if="isDraft" permission="edit invoices">
                <button type="button" class="btn btn-secondary w-full justify-start" @click="emit('issue')">
                    <CheckBadgeIcon />
                    {{ t('invoice_action_issue') }}
                </button>
            </Can>

            <Can v-if="isIssuedOrOverdue" permission="edit invoices">
                <button type="button" class="btn btn-primary w-full justify-start" @click="emit('pay')">
                    <BanknotesIcon />
                    {{
                        t(
                            props.invoice.is_credit_note
                                ? 'invoice_action_record_refund'
                                : 'invoice_action_record_payment',
                        )
                    }}
                </button>
            </Can>

            <button type="button" class="btn btn-neutral w-full justify-start" @click="emit('preview')">
                <EyeIcon />
                {{ t('invoice_action_preview') }}
            </button>

            <a
                :href="`/invoices/${props.invoice.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-neutral w-full justify-start"
            >
                <ArrowDownTrayIcon />
                {{ t('invoice_action_download_pdf') }}
            </a>

            <Can v-if="isIssuedOrOverdue" permission="edit invoices">
                <div>
                    <button
                        type="button"
                        class="btn btn-primary w-full justify-start"
                        :disabled="!canSend"
                        :title="!canSend ? t('invoice_send_requires_email') : undefined"
                        @click="emit('send')"
                    >
                        <EnvelopeIcon />
                        {{ t('invoice_action_send_email') }}
                    </button>
                    <p v-if="!canSend" class="mt-1 pl-1 text-xs text-muted">
                        {{ t('invoice_send_requires_email') }}
                    </p>
                </div>
            </Can>

            <Can permission="create invoices">
                <button type="button" class="btn btn-neutral w-full justify-start" @click="emit('duplicate')">
                    <DocumentDuplicateIcon />
                    {{ t('invoice_action_duplicate') }}
                </button>
            </Can>

            <Can v-if="isIssuedOrOverdue" permission="cancel invoices">
                <button type="button" class="btn btn-error w-full justify-start" @click="emit('cancel')">
                    <XCircleIcon />
                    {{ t('invoice_action_cancel') }}
                </button>
            </Can>

            <Can v-if="isDraft" permission="cancel invoices">
                <button type="button" class="btn btn-error w-full justify-start" @click="emit('delete')">
                    <TrashIcon />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
