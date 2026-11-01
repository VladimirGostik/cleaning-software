<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { differenceInCalendarDays, parseISO, startOfToday } from 'date-fns';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractPartiesCard from '@/Components/Contracts/ContractPartiesCard.vue';
import ContractTermCard from '@/Components/Contracts/ContractTermCard.vue';
import ContractEmploymentCard from '@/Components/Contracts/ContractEmploymentCard.vue';
import ContractActionsCard from '@/Components/Contracts/ContractActionsCard.vue';
import ContractLinksCard from '@/Components/Contracts/ContractLinksCard.vue';
import ContractTerminateModal from '@/Components/Contracts/ContractTerminateModal.vue';
import DocumentMissingTokens from '@/Components/Documents/DocumentMissingTokens.vue';
import DocumentSheet from '@/Components/Documents/DocumentSheet.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { formatDate, formatDatetime } from '@/utils/date';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('contracts'), url: '/contracts' },
    { label: props.contract.title },
]);

const ui = reactive({
    terminateOpen: false,
});

const daysToEnd = computed(() =>
    props.contract.end_date ? differenceInCalendarDays(parseISO(props.contract.end_date), startOfToday()) : null,
);

const showExpiringWarning = computed(
    () =>
        props.contract.status === 'active' &&
        props.contract.term_type === 'fixed' &&
        daysToEnd.value !== null &&
        daysToEnd.value >= 0 &&
        daysToEnd.value <= 30,
);

const missingTokens = computed(() => {
    const labels = new Map(props.contract.tokens.map((def) => [def.key, def.label]));

    return props.contract.missing_tokens.map((key) => ({ key, label: labels.get(key) ?? key }));
});

const missingSummary = computed(() =>
    t('document_missing_summary', { count: missingTokens.value.length }, missingTokens.value.length),
);

const signConfirm = useDeleteConfirm<App.Data.Contracts.ContractDetailData>({
    method: 'post',
    resolveUrl: (c) => `/contracts/${c.id}/sign`,
    getTitle: () => t('contract_action_sign'),
    getDescription: (c) => t('contract_sign_confirm', { title: c.title }),
});

const deleteConfirm = useDeleteConfirm<App.Data.Contracts.ContractDetailData>({
    method: 'delete',
    resolveUrl: (c) => `/contracts/${c.id}`,
    getTitle: () => t('delete'),
    getDescription: () => t('contract_delete_confirm'),
});
</script>

<template>
    <Header :title="contract.title" :breadcrumbs="breadcrumbs">
        <template #actions>
            <ContractStatusBadge :status="contract.status" />
            <ContractCategoryBadge :category="contract.category" />
        </template>
    </Header>

    <div v-if="showExpiringWarning" class="alert alert-warning mb-4">
        <span>
            {{ t('contract_expiring_warning', { days: daysToEnd, date: formatDate(contract.end_date) }) }}
        </span>
    </div>

    <div v-if="contract.status === 'terminated'" class="alert alert-error mb-4">
        <span>{{ t('contract_terminated_title') }}: {{ formatDatetime(contract.terminated_at) }}</span>
    </div>

    <StatusErrorAlert class="mb-4" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="flex flex-col gap-6 [&>.card:last-child]:flex-1">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-6">
                    <div>
                        <h2 class="card-title text-base">{{ t('contract_section_parties') }}</h2>
                        <ContractPartiesCard :contract="contract" />
                    </div>

                    <div>
                        <h2 class="card-title text-base">{{ t('contract_section_term') }}</h2>
                        <ContractTermCard :contract="contract" />
                    </div>
                </div>
            </div>

            <div v-if="contract.employment" class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('contract_section_employment') }}</h2>
                    <ContractEmploymentCard :employment="contract.employment" />
                </div>
            </div>

            <div v-if="contract.notes" class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ t('contract_section_notes') }}</h2>
                    <p class="whitespace-pre-wrap text-sm text-base-content/70">{{ contract.notes }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <ContractActionsCard
                :contract="contract"
                @sign="signConfirm.openModal(contract)"
                @terminate="ui.terminateOpen = true"
                @delete="deleteConfirm.openModal(contract)"
            />
            <ContractLinksCard :contract="contract" />
        </div>
    </div>

    <div class="card bg-base-100 mt-6 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('contract_section_body') }}</h2>

            <div v-if="contract.body === null" class="alert alert-warning" role="alert">
                <span>{{ t('contract_text_no_access') }}</span>
            </div>

            <template v-else>
                <template v-if="missingTokens.length > 0">
                    <DocumentMissingTokens :tokens="missingTokens" :summary="missingSummary" />
                    <p class="text-sm text-base-content/60">{{ t('contract_sign_blocked_missing') }}</p>
                </template>

                <div class="overflow-hidden rounded-box border border-base-300">
                    <DocumentSheet
                        :html="contract.body"
                        :tokens="contract.tokens"
                        :letterhead="contract.show_header ? contract.letterhead : null"
                        :footer="contract.show_footer ? contract.footer : []"
                    />
                </div>
            </template>
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="signConfirm.state.isOpen"
        :title="signConfirm.getModalTitle()"
        :description="signConfirm.getModalDescription()"
        :confirm-label="t('contract_action_sign')"
        confirm-variant="success"
        @cancel="signConfirm.closeModal"
        @confirm="signConfirm.confirmDelete"
    />

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        :confirm-label="t('delete')"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />

    <ContractTerminateModal :open="ui.terminateOpen" :contract-id="contract.id" @close="ui.terminateOpen = false" />
</template>
