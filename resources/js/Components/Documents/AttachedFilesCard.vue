<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import AttachedFileUploadForm, { type AttachedFileConstraints } from './AttachedFileUploadForm.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { documentTypeKey } from '@/utils/enums';
import { formatDate } from '@/utils/date';
import { formatBytes } from '@/utils/bytes';

interface AttachedFile {
    id: string;
    title: string;
    file_name: string;
    mime_type: string | null;
    size: number;
    created_at: string;
    download_url: string;
    type?: App.Enums.DocumentTypeEnum;
    can_delete?: boolean;
}

const props = defineProps<{
    heading: string;
    documents: AttachedFile[];
    uploadUrl: string;
    deleteUrl: (id: string) => string;
    constraints: AttachedFileConstraints | null;
    canManage: boolean;
    titleMaxLength: number;
}>();

const { t } = useI18n();

const limitReached = computed(
    () => props.constraints?.max_count !== undefined && props.documents.length >= props.constraints.max_count,
);

function metaLine(doc: AttachedFile): string {
    return [
        doc.type ? t(documentTypeKey(doc.type)) : null,
        doc.file_name,
        formatBytes(doc.size),
        formatDate(doc.created_at),
    ]
        .filter((part) => part !== null)
        .join(' · ');
}

const deleteConfirm = useDeleteConfirm<AttachedFile>({
    resolveUrl: (d) => props.deleteUrl(d.id),
    getTitle: () => t('attached_file_delete'),
    getDescription: (d) => t('attached_file_delete_confirm', { name: d.title }),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ heading }}</h2>

            <p v-if="documents.length === 0" class="text-sm text-base-content/60">
                {{ t('attached_files_empty') }}
            </p>

            <ul v-else class="divide-y divide-base-200">
                <li v-for="doc in documents" :key="doc.id" class="flex flex-wrap items-center gap-2 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ doc.title }}</p>
                        <p class="text-xs text-base-content/60">{{ metaLine(doc) }}</p>
                    </div>
                    <a
                        :href="doc.download_url"
                        class="btn btn-ghost btn-sm btn-square"
                        :aria-label="t('attached_file_download')"
                        :title="t('attached_file_download')"
                    >
                        <ArrowDownTrayIcon class="size-5" />
                    </a>
                    <button
                        v-if="canManage && doc.can_delete !== false"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square text-error"
                        :aria-label="t('attached_file_delete')"
                        :title="t('attached_file_delete')"
                        @click="deleteConfirm.openModal(doc)"
                    >
                        <TrashIcon class="size-5" />
                    </button>
                </li>
            </ul>

            <template v-if="canManage && constraints">
                <p v-if="limitReached" class="text-sm text-base-content/60">
                    {{ t('attached_files_limit_reached', { max: constraints.max_count }) }}
                </p>
                <AttachedFileUploadForm
                    v-else
                    :upload-url="uploadUrl"
                    :constraints="constraints"
                    :title-max-length="titleMaxLength"
                />
            </template>
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        :confirm-label="t('attached_file_delete')"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
