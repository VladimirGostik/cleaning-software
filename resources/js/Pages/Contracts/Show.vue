<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { differenceInCalendarDays, parseISO, startOfToday } from 'date-fns';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractPartiesCard from '@/Components/Contracts/ContractPartiesCard.vue';
import ContractTermCard from '@/Components/Contracts/ContractTermCard.vue';
import ContractBodyPreview from '@/Components/Contracts/ContractBodyPreview.vue';
import ContractEmploymentCard from '@/Components/Contracts/ContractEmploymentCard.vue';
import ContractActionsCard from '@/Components/Contracts/ContractActionsCard.vue';
import ContractLinksCard from '@/Components/Contracts/ContractLinksCard.vue';
import ContractTerminateModal from '@/Components/Contracts/ContractTerminateModal.vue';

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
    <AppLayout>
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
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

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('contract_section_body') }}</h2>
                        <ContractBodyPreview :body="contract.body" />
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
    </AppLayout>
</template>
