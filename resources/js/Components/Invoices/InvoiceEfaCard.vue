<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowDownTrayIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';

import Can from '@/Components/Can.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import EfaStatusBadge from './EfaStatusBadge.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDatetime } from '@/utils/date';

const props = defineProps<{
    invoice: App.Data.Invoices.InvoiceDetailData;
}>();

const { t } = useI18n();
const page = usePage();

const efa = computed(() => props.invoice.efa);
const submitError = computed(() => (page.props.errors as Record<string, string | undefined>).efa);
const canDownload = computed(() => efa.value.missing_fields.length === 0);

const submitConfirm = useDeleteConfirm<App.Data.Invoices.InvoiceDetailData>({
    method: 'post',
    resolveUrl: (i) => `/invoices/${i.id}/efa-submit`,
    getTitle: () => t('efa_card_submit_confirm_title'),
    getDescription: () => t('efa_card_submit_confirm_body'),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3">
            <h2 class="card-title text-base">{{ t('efa_card_title') }}</h2>

            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="text-base-content/60">{{ t('efa_card_status') }}</span>
                <EfaStatusBadge :status="efa.status" />
            </div>

            <div v-if="efa.submitted_at" class="flex justify-between gap-2 text-sm">
                <span class="text-base-content/60">{{ t('efa_card_submitted_at') }}</span>
                <span>{{ formatDatetime(efa.submitted_at) }}</span>
            </div>

            <div v-if="efa.efa_id" class="flex justify-between gap-2 text-sm">
                <span class="text-base-content/60">{{ t('efa_card_provider_id') }}</span>
                <span class="break-all text-right">{{ efa.efa_id }}</span>
            </div>

            <div v-if="efa.last_error" class="alert alert-error text-sm">
                <div>
                    <p class="font-medium">{{ t('efa_card_last_error') }}</p>
                    <p class="break-words">{{ efa.last_error }}</p>
                </div>
            </div>

            <div v-if="efa.missing_fields.length > 0" class="alert alert-warning text-sm">
                <div>
                    <p class="font-medium">{{ t('efa_card_missing_title') }}</p>
                    <ul class="list-disc pl-4">
                        <li v-for="field in efa.missing_fields" :key="field">{{ t(`efa_missing_${field}`) }}</li>
                    </ul>
                </div>
            </div>

            <p v-if="submitError" class="text-sm text-error" role="alert">{{ submitError }}</p>

            <a v-if="canDownload" :href="`/invoices/${invoice.id}/efa-xml`" class="btn btn-sm w-full justify-start">
                <ArrowDownTrayIcon class="size-4" />
                {{ t('efa_card_download_xml') }}
            </a>

            <Can v-if="efa.can_submit" permission="edit invoices">
                <button
                    type="button"
                    class="btn btn-sm btn-primary w-full justify-start"
                    @click="submitConfirm.openModal(invoice)"
                >
                    <PaperAirplaneIcon class="size-4" />
                    {{ t('efa_card_submit') }}
                </button>
            </Can>
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="submitConfirm.state.isOpen"
        :title="submitConfirm.getModalTitle()"
        :description="submitConfirm.getModalDescription()"
        :confirm-label="t('efa_card_submit')"
        confirm-variant="primary"
        @cancel="submitConfirm.closeModal"
        @confirm="submitConfirm.confirmDelete"
    />
</template>
