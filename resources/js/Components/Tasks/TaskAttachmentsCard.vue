<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import CameraIcon from '~icons/material-symbols/photo-camera-outline-rounded';
import DocumentIcon from '~icons/material-symbols/description-outline-rounded';
import ExclamationTriangleIcon from '~icons/material-symbols/warning-outline-rounded';
import PaperClipIcon from '~icons/material-symbols/attach-file-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import XMarkIcon from '~icons/material-symbols/close-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import FilePickerButton from '@/Components/Forms/FilePickerButton.vue';
import TaskPanelSection from './TaskPanelSection.vue';
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
const hintId = `task-attachment-hint-${props.taskId}`;
const constraintsHint = computed(() =>
    t('task_attachment_hint', {
        size: formatAttachmentLimit(props.list.constraints.max_size_kb),
        count: props.list.constraints.max_count,
    }),
);

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
    <TaskPanelSection :title="t('task_attachments')">
        <template v-if="list.can_add" #actions>
            <FilePickerButton
                class="btn btn-ghost btn-sm"
                :label="t('task_attachment_add_short')"
                :accept="accept"
                multiple
                :disabled="atLimit"
                :title="constraintsHint"
                :aria-describedby="hintId"
                @selected="emit('add', $event)"
            >
                <PaperClipIcon />
            </FilePickerButton>
            <FilePickerButton
                class="btn btn-ghost btn-sm hidden pointer-coarse:inline-flex"
                :label="t('task_attachment_take_photo')"
                accept="image/*"
                capture="environment"
                :disabled="atLimit"
                @selected="onCamera"
            >
                <CameraIcon />
            </FilePickerButton>
        </template>

        <span :id="hintId" class="sr-only">{{ constraintsHint }}</span>
        <p v-if="list.can_add && atLimit" class="text-xs text-muted">
            {{ t('task_attachment_limit_hint', { max: list.constraints.max_count }) }}
        </p>

        <ul v-if="items.length > 0" class="kg-list">
            <li v-for="item in items" :key="item.id" class="kg-list-row text-sm">
                <div class="min-w-0 flex-1">
                    <p class="truncate">{{ item.name }}</p>
                    <progress
                        v-if="item.status === 'uploading'"
                        class="progress progress-primary mt-1 w-full"
                        :value="item.progress"
                        max="100"
                    />
                    <p v-else-if="item.status === 'queued'" class="text-xs text-muted">
                        {{ t('loading') }}
                    </p>
                    <p v-else role="alert" class="flex items-center gap-1 text-sm text-error">
                        <ExclamationTriangleIcon class="shrink-0" />
                        {{ item.error }}
                    </p>
                </div>
                <button
                    v-if="item.status === 'failed'"
                    type="button"
                    class="btn btn-ghost btn-square"
                    :aria-label="t('remove_file')"
                    :title="t('remove_file')"
                    @click="emit('dismiss', item.id)"
                >
                    <XMarkIcon />
                </button>
            </li>
        </ul>

        <ul v-if="images.length > 0" class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
            <li v-for="image in images" :key="image.id" class="group relative">
                <button
                    type="button"
                    class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-tile bg-base-200"
                    :aria-label="t('task_attachment_open_preview', { name: image.name })"
                    :title="image.name"
                    @click="previewId = image.id"
                >
                    <DocumentIcon v-if="brokenTiles.has(image.id)" class="text-muted" />
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
                    class="btn btn-circle absolute right-1 top-1 bg-base-100/90 text-error"
                    :disabled="uploading"
                    :aria-label="t('task_attachment_delete')"
                    :title="t('task_attachment_delete')"
                    @click="deleteConfirm.openModal(image)"
                >
                    <TrashIcon />
                </button>
            </li>
        </ul>

        <ul v-if="files.length > 0" class="kg-list">
            <li v-for="file in files" :key="file.id" class="kg-list-row flex-wrap">
                <DocumentIcon class="shrink-0 text-muted" />
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
                    <p class="text-xs text-muted">{{ meta(file) }}</p>
                </div>
                <a
                    :href="file.download_url"
                    download
                    class="btn btn-ghost btn-square"
                    :aria-label="t('task_attachment_download')"
                    :title="t('task_attachment_download')"
                >
                    <ArrowDownTrayIcon />
                </a>
                <button
                    v-if="file.can_delete"
                    type="button"
                    class="btn btn-ghost btn-square text-error"
                    :disabled="uploading"
                    :aria-label="t('task_attachment_delete')"
                    :title="t('task_attachment_delete')"
                    @click="deleteConfirm.openModal(file)"
                >
                    <TrashIcon />
                </button>
            </li>
        </ul>
    </TaskPanelSection>

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
