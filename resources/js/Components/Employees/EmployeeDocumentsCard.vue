<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ArrowDownTrayIcon, TrashIcon } from '@heroicons/vue/24/outline';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import EmployeeDocumentUploadForm from './EmployeeDocumentUploadForm.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { documentTypeKey } from '@/utils/enums';
import { formatDate } from '@/utils/date';
import { formatBytes } from '@/utils/bytes';

type EmployeeDocument = App.Data.Employees.EmployeeDocumentData;

const props = defineProps<{
    employeeId: string;
    documents: EmployeeDocument[];
    constraints: App.Data.Employees.EmployeeDocumentConstraintsData | null;
    canManage: boolean;
}>();

const { t } = useI18n();

const deleteConfirm = useDeleteConfirm<EmployeeDocument>({
    resolveUrl: (d) => `/employees/${props.employeeId}/documents/${d.id}`,
    getTitle: () => t('employee_document_delete'),
    getDescription: (d) => t('employee_document_delete_confirm', { name: d.title }),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('employee_section_documents') }}</h2>

            <p v-if="documents.length === 0" class="text-sm text-base-content/60">
                {{ t('employee_documents_empty') }}
            </p>

            <ul v-else class="divide-y divide-base-200">
                <li v-for="doc in documents" :key="doc.id" class="flex flex-wrap items-center gap-2 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ doc.title }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ t(documentTypeKey(doc.type)) }} · {{ doc.file_name }} · {{ formatBytes(doc.size) }} ·
                            {{ formatDate(doc.created_at) }}
                        </p>
                    </div>
                    <a
                        :href="doc.download_url"
                        class="btn btn-ghost btn-sm btn-square"
                        :aria-label="t('employee_document_download')"
                        :title="t('employee_document_download')"
                    >
                        <ArrowDownTrayIcon class="size-4" />
                    </a>
                    <button
                        v-if="canManage"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square text-error"
                        :aria-label="t('employee_document_delete')"
                        :title="t('employee_document_delete')"
                        @click="deleteConfirm.openModal(doc)"
                    >
                        <TrashIcon class="size-4" />
                    </button>
                </li>
            </ul>

            <EmployeeDocumentUploadForm
                v-if="canManage && constraints"
                :employee-id="employeeId"
                :constraints="constraints"
            />
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        :confirm-label="t('employee_document_delete')"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
