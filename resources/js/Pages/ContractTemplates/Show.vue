<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import Can from '@/Components/Can.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractBodyPreview from '@/Components/Contracts/ContractBodyPreview.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    template: App.Data.ContractTemplates.ContractTemplateDetailData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('contract_templates'), url: '/contract-templates' },
    { label: props.template.name },
]);

const deleteConfirm = useDeleteConfirm<App.Data.ContractTemplates.ContractTemplateDetailData>({
    method: 'delete',
    resolveUrl: (tpl) => `/contract-templates/${tpl.id}`,
    getTitle: () => t('delete'),
    getDescription: (tpl) => t('contract_template_delete_confirm', { name: tpl.name }),
});
</script>

<template>
    <AppLayout>
        <Header :title="template.name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <ContractCategoryBadge :category="template.category" />
                <ObjectStatusBadge :is-active="template.is_active" />

                <Can permission="edit contract_templates">
                    <a :href="`/contract-templates/${template.id}/edit`" class="btn btn-sm">{{ t('edit') }}</a>
                </Can>

                <Can permission="delete contract_templates">
                    <button type="button" class="btn btn-sm text-error" @click="deleteConfirm.openModal(template)">
                        {{ t('delete') }}
                    </button>
                </Can>
            </template>
        </Header>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ t('contract_template_section_body') }}</h2>
                <ContractBodyPreview :body="template.body" />
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="deleteConfirm.state.isOpen"
            :title="deleteConfirm.getModalTitle()"
            :description="deleteConfirm.getModalDescription()"
            :confirm-label="t('delete')"
            @cancel="deleteConfirm.closeModal"
            @confirm="deleteConfirm.confirmDelete"
        />
    </AppLayout>
</template>
