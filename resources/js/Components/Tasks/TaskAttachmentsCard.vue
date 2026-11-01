<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import CameraIcon from '~icons/material-symbols/photo-camera-outline-rounded';
import DocumentIcon from '~icons/material-symbols/description-outline-rounded';
import ExclamationTriangleIcon from '~icons/material-symbols/warning-outline-rounded';
import PaperClipIcon from '~icons/material-symbols/attach-file-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import XMarkIcon from '~icons/material-symbols/close-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import FilePickerButton from '@/Components/Forms/FilePickerButton.vue';
import TaskAttachmentPreviewModal from './TaskAttachmentPreviewModal.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { TaskAttachmentUploadItem } from '@/Composables/useTaskAttachmentUpload';
import { formatBytes } from '@/utils/bytes';
import { formatDate } from '@/utils/date';
import { formatAttachmentLimit, taskAttachmentAccept, withCameraName } from '@/utils/taskAttachments';

type Attachment = App.Data.Tasks.TaskAttachmentData;

const props = defineProps<{
    taskId: string;
    list: App.Data.Tasks.TaskAttachmentListData;
    items: TaskAttachmentUploadItem[];
    uploading: boolean;
}>();

const emit = defineEmits<{ add: [files: File[]]; dismiss: [id: string] }>();

const { t } = useI18n();

const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
const accept = computed(() => taskAttachmentAccept(props.list.constraints, coarsePointer));
const atLimit = computed(() => props.list.attachments.length >= props.list.constraints.max_count);
const images = computed(() => props.list.attachments.filter((attachment) => attachment.previewable));
const files = computed(() => props.list.attachments.filter((attachment) => !attachment.previewable));
const isEmpty = computed(() => props.list.attachments.length === 0 && props.items.length === 0);

const previewId = ref<string | null>(null);
const brokenTiles = ref<Set<string>>(new Set());

function markBroken(id: string): void {
    brokenTiles.value = new Set(brokenTiles.value).add(id);
}

function onCamera(selected: File[]): void {
    const now = Date.now();

    emit(
        'add',
        selected.map((file, index) => withCameraName(file, now, index)),
    );
}

function isPdf(attachment: Attachment): boolean {
    return attachment.mime_type === 'application/pdf';
}

function meta(attachment: Attachment): string {
    const parts = [formatBytes(attachment.size)];

    if (attachment.uploaded_by_name) {
        parts.push(t('task_attachment_uploaded_by', { name: attachment.uploaded_by_name }));
    }

    parts.push(formatDate(attachment.created_at));

    return parts.join(' · ');
}

const deleteConfirm = useDeleteConfirm<Attachment>({
    resolveUrl: (a) => `/tasks/${props.taskId}/attachments/${a.id}`,
    getTitle: () => t('task_attachment_delete'),
    getDescription: (a) => t('task_attachment_delete_confirm', { name: a.name }),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="card-title flex-1 text-base">{{ t('task_attachments') }}</h2>

                <template v-if="list.can_add">
                    <FilePickerButton
                        class="btn btn-primary btn-sm"
                        :label="t('task_attachment_add')"
                        :accept="accept"
                        multiple
                        :disabled="atLimit"
                        @selected="emit('add', $event)"
                    >
                        <PaperClipIcon class="size-5" />
                    </FilePickerButton>
                    <FilePickerButton
                        class="btn btn-neutral btn-sm hidden pointer-coarse:inline-flex"
                        :label="t('task_attachment_take_photo')"
                        accept="image/*"
                        capture="environment"
                        :disabled="atLimit"
                        @selected="onCamera"
                    >
                        <CameraIcon class="size-5" />
                    </FilePickerButton>
                </template>
            </div>

            <p v-if="list.can_add" class="text-xs text-base-content/60">
                {{
                    atLimit
                        ? t('task_attachment_limit_hint', { max: list.constraints.max_count })
                        : t('task_attachment_hint', {
                              size: formatAttachmentLimit(list.constraints.max_size_kb),
                              count: list.constraints.max_count,
                          })
                }}
            </p>

            <p v-if="isEmpty" class="text-sm text-base-content/60">{{ t('task_attachments_empty') }}</p>

            <ul v-if="items.length > 0" class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center gap-2 rounded-box bg-base-200 px-3 py-2 text-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate">{{ item.name }}</p>
                        <progress
                            v-if="item.status === 'uploading'"
                            class="progress progress-primary mt-1 w-full"
                            :value="item.progress"
                            max="100"
                        />
                        <p v-else-if="item.status === 'queued'" class="text-xs text-base-content/60">
                            {{ t('loading') }}
                        </p>
                        <p v-else role="alert" class="flex items-center gap-1 text-xs text-error">
                            <ExclamationTriangleIcon class="size-5 shrink-0" />
                            {{ item.error }}
                        </p>
                    </div>
                    <button
                        v-if="item.status === 'failed'"
                        type="button"
                        class="btn btn-ghost btn-xs btn-square"
                        :aria-label="t('remove_file')"
                        :title="t('remove_file')"
                        @click="emit('dismiss', item.id)"
                    >
                        <XMarkIcon class="size-5" />
                    </button>
                </li>
            </ul>

            <ul v-if="images.length > 0" class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                <li v-for="image in images" :key="image.id" class="group relative">
                    <button
                        type="button"
                        class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-box bg-base-200"
                        :aria-label="t('task_attachment_open_preview', { name: image.name })"
                        :title="image.name"
                        @click="previewId = image.id"
                    >
                        <DocumentIcon v-if="brokenTiles.has(image.id)" class="size-8 text-base-content/40" />
                        <img
                            v-else
                            :src="image.download_url"
                            :alt="image.name"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover"
                            @error="markBroken(image.id)"
                        />
                    </button>
                    <button
                        v-if="image.can_delete"
                        type="button"
                        class="btn btn-circle btn-xs absolute right-1 top-1 bg-base-100/90 text-error"
                        :disabled="uploading"
                        :aria-label="t('task_attachment_delete')"
                        :title="t('task_attachment_delete')"
                        @click="deleteConfirm.openModal(image)"
                    >
                        <TrashIcon class="size-5" />
                    </button>
                </li>
            </ul>

            <ul v-if="files.length > 0" class="divide-y divide-base-200">
                <li v-for="file in files" :key="file.id" class="flex flex-wrap items-center gap-2 py-2">
                    <DocumentIcon class="size-5 shrink-0 text-base-content/60" />
                    <div class="min-w-0 flex-1">
                        <a
                            v-if="isPdf(file)"
                            :href="file.download_url"
                            target="_blank"
                            rel="noopener"
                            class="link link-hover block truncate font-medium"
                        >
                            {{ file.name }}
                        </a>
                        <a v-else :href="file.download_url" download class="link link-hover block truncate font-medium">
                            {{ file.name }}
                        </a>
                        <p class="text-xs text-base-content/60">{{ meta(file) }}</p>
                    </div>
                    <a
                        :href="file.download_url"
                        download
                        class="btn btn-ghost btn-sm btn-square"
                        :aria-label="t('task_attachment_download')"
                        :title="t('task_attachment_download')"
                    >
                        <ArrowDownTrayIcon class="size-5" />
                    </a>
                    <button
                        v-if="file.can_delete"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square text-error"
                        :disabled="uploading"
                        :aria-label="t('task_attachment_delete')"
                        :title="t('task_attachment_delete')"
                        @click="deleteConfirm.openModal(file)"
                    >
                        <TrashIcon class="size-5" />
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <TaskAttachmentPreviewModal v-model:active-id="previewId" :images="images" />

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        :confirm-label="t('task_attachment_delete')"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
