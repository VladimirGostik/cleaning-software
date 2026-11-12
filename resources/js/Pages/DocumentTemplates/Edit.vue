<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import DocumentTemplateBaseChanged from '@/Components/DocumentTemplates/DocumentTemplateBaseChanged.vue';
import DocumentTemplateScopeBadge from '@/Components/DocumentTemplates/DocumentTemplateScopeBadge.vue';
import DocumentTemplateStateBadge from '@/Components/DocumentTemplates/DocumentTemplateStateBadge.vue';
import DocumentTemplateWorkspace from '@/Components/DocumentTemplates/DocumentTemplateWorkspace.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    template: App.Data.DocumentTemplates.DocumentTemplateEditData;
}>();

const { t } = useI18n();

const baseUrl = computed(() => `/settings/document-templates/${props.template.id}`);

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('document_templates'), url: '/settings/document-templates' },
    { label: props.template.name },
]);

const reset = useDeleteConfirm<App.Data.DocumentTemplates.DocumentTemplateEditData>({
    method: 'post',
    resolveUrl: (item) => `/settings/document-templates/${item.id}/reset`,
    getTitle: () => t('document_template_reset_title'),
    getDescription: (item) => t('document_template_reset_confirm', { name: item.name }),
});

const remove = useDeleteConfirm<App.Data.DocumentTemplates.DocumentTemplateEditData>({
    method: 'delete',
    resolveUrl: (item) => `/settings/document-templates/${item.id}`,
    getTitle: () => t('delete'),
    getDescription: (item) => t('document_template_delete_confirm', { name: item.name }),
});

const processing = ref(false);

function post(path: 'customize' | 'acknowledge'): void {
    router.post(
        `${baseUrl.value}/${path}`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Header :title="template.name" :breadcrumbs="breadcrumbs">
        <template #actions>
            <DocumentTemplateScopeBadge :scope="template.scope" />
            <DocumentTemplateStateBadge v-if="template.state" :state="template.state" />
            <button
                v-if="template.can.customize"
                type="button"
                class="btn btn-primary"
                :disabled="processing"
                @click="post('customize')"
            >
                {{ t('document_template_customize') }}
            </button>
            <button v-if="template.can.reset" type="button" class="btn btn-ghost" @click="reset.openModal(template)">
                {{ t('document_template_reset_action') }}
            </button>
            <button v-if="template.can.delete" type="button" class="btn btn-error" @click="remove.openModal(template)">
                {{ t('delete') }}
            </button>
        </template>
    </Header>

    <div v-if="!template.can.update && template.can.customize" class="alert alert-info alert-soft mb-6" role="status">
        {{ t('document_template_global_readonly_hint') }}
    </div>

    <DocumentTemplateBaseChanged
        v-if="template.state === 'base_changed' && template.base_body !== null"
        :previous-base-body="template.previous_base_body"
        :base-body="template.base_body"
        :tokens="template.tokens"
        :processing="processing"
        :can-acknowledge="template.can.acknowledge"
        :can-reset="template.can.reset"
        @keep="post('acknowledge')"
        @restore="reset.openModal(template)"
    />

    <DocumentTemplateWorkspace
        :key="template.id"
        :template="template"
        :letterhead="template.letterhead"
        :footer="template.footer"
        :quote-footer="template.quote_footer"
    />

    <ConfirmDeleteModal
        :is-open="reset.state.isOpen"
        :title="reset.getModalTitle()"
        :description="reset.getModalDescription()"
        :confirm-label="t('document_template_reset_action')"
        confirm-variant="warning"
        @cancel="reset.closeModal"
        @confirm="reset.confirmDelete"
    />

    <ConfirmDeleteModal
        :is-open="remove.state.isOpen"
        :title="remove.getModalTitle()"
        :description="remove.getModalDescription()"
        :confirm-label="t('delete')"
        @cancel="remove.closeModal"
        @confirm="remove.confirmDelete"
    />
</template>
