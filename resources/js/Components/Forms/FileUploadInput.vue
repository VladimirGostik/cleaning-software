<script setup lang="ts">
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import CloseIcon from '~icons/material-symbols/close-outline-rounded';
import FileIcon from '~icons/material-symbols/description-outline-rounded';
import UploadIcon from '~icons/material-symbols/upload-file-outline-rounded';
import { reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatBytes } from '@/utils/bytes';

export interface InitialFile {
    uuid: string;
    name: string;
    url: string;
    mime_type?: string | null;
    size?: number | null;
}

interface UploadItem {
    id: string;
    uuid: string | null;
    name: string;
    size: number;
    mimeType: string;
    localUrl: string | null;
    progress: number;
    status: 'pending' | 'uploading' | 'done' | 'error';
    error: string | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | string[] | null;
        initialFiles?: InitialFile[];
        multiple?: boolean;
        accept?: string;
        maxFiles?: number;
        maxSizeKb?: number;
        endpoint?: string;
        disabled?: boolean;
        error?: string | null;
    }>(),
    {
        multiple: false,
        accept: '*/*',
        maxFiles: undefined,
        maxSizeKb: 10240,
        endpoint: '/uploads',
        disabled: false,
        error: null,
        initialFiles: () => [],
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | string[] | null];
}>();

const { t } = useI18n();

const effectiveMaxFiles = computed(() => props.maxFiles ?? (props.multiple ? 20 : 1));

const items = reactive<UploadItem[]>([]);
let nextId = 0;

props.initialFiles.forEach((f) => {
    items.push({
        id: String(nextId++),
        uuid: f.uuid,
        name: f.name,
        size: f.size ?? 0,
        mimeType: f.mime_type ?? '',
        localUrl: f.url,
        progress: 100,
        status: 'done',
        error: null,
    });
});

const isDragging = reactive({ value: false });

const isImage = (mimeType: string) => mimeType.startsWith('image/');

function emitValue(): void {
    const doneUuids = items.filter((i) => i.status === 'done' && i.uuid).map((i) => i.uuid as string);
    if (props.multiple) {
        emit('update:modelValue', doneUuids);
    } else {
        emit('update:modelValue', doneUuids[0] ?? null);
    }
}

function validateFile(file: File): string | null {
    if (file.size > props.maxSizeKb * 1024) {
        return t('file_too_large', { max: formatBytes(props.maxSizeKb * 1024) });
    }
    if (props.accept !== '*/*') {
        const accepted = props.accept.split(',').map((a) => a.trim());
        const isAccepted = accepted.some((pattern) => {
            if (pattern.endsWith('/*')) {
                return file.type.startsWith(pattern.slice(0, -1));
            }
            return file.type === pattern || file.name.endsWith(pattern.replace('*', ''));
        });
        if (!isAccepted) {
            return t('file_type_not_allowed');
        }
    }
    return null;
}

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function uploadItem(item: UploadItem, file: File): void {
    item.status = 'uploading';
    const formData = new FormData();
    formData.append('file', file);

    const xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            item.progress = Math.round((e.loaded / e.total) * 100);
        }
    });
    xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            const res = JSON.parse(xhr.responseText) as { uuid: string; url: string };
            item.uuid = res.uuid;
            item.localUrl = res.url;
            item.status = 'done';
            emitValue();
        } else {
            item.status = 'error';
            item.error = t('upload_failed');
        }
    });
    xhr.addEventListener('error', () => {
        item.status = 'error';
        item.error = t('upload_failed');
    });
    xhr.open('POST', props.endpoint);
    xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.send(formData);
}

function addFiles(files: FileList | File[]): void {
    const arr = Array.from(files);
    const remaining = effectiveMaxFiles.value - items.length;
    if (remaining <= 0) {
        return;
    }
    const toAdd = arr.slice(0, remaining);

    toAdd.forEach((file) => {
        const validationError = validateFile(file);
        const item: UploadItem = {
            id: String(nextId++),
            uuid: null,
            name: file.name,
            size: file.size,
            mimeType: file.type,
            localUrl: isImage(file.type) ? URL.createObjectURL(file) : null,
            progress: 0,
            status: validationError ? 'error' : 'pending',
            error: validationError,
        };
        items.push(item);
        if (!validationError) {
            uploadItem(item, file);
        }
    });

    if (!props.multiple) {
        while (items.length > 1) {
            items.splice(0, 1);
        }
    }
}

function removeItem(id: string): void {
    const idx = items.findIndex((i) => i.id === id);
    if (idx !== -1) {
        const item = items[idx];
        if (item.localUrl && item.localUrl.startsWith('blob:')) {
            URL.revokeObjectURL(item.localUrl);
        }
        if (item.uuid) {
            const xhr = new XMLHttpRequest();
            xhr.open('DELETE', `${props.endpoint}/${item.uuid}`);
            xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send();
        }
        items.splice(idx, 1);
        emitValue();
    }
}

function onDragEnter(e: DragEvent): void {
    e.preventDefault();
    isDragging.value = true;
}

function onDragOver(e: DragEvent): void {
    e.preventDefault();
}

function onDragLeave(e: DragEvent): void {
    e.preventDefault();
    isDragging.value = false;
}

function onDrop(e: DragEvent): void {
    e.preventDefault();
    isDragging.value = false;
    if (!props.disabled && e.dataTransfer?.files) {
        addFiles(e.dataTransfer.files);
    }
}

function onFileInputChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    if (input.files) {
        addFiles(input.files);
        input.value = '';
    }
}

function openFilePicker(): void {
    if (!props.disabled) {
        document.getElementById(`file-input-${props.endpoint.replace(/\//g, '-')}`)?.click();
    }
}

watch(
    () => props.modelValue,
    (newVal) => {
        if (newVal === null || (Array.isArray(newVal) && newVal.length === 0)) {
            items.splice(0, items.length);
        }
    },
);
</script>

<template>
    <div class="space-y-3">
        <button
            type="button"
            class="flex w-full flex-col items-center gap-1 rounded-card-nested bg-(--kg-control-bg) p-6 text-center transition-colors"
            :class="[
                isDragging.value ? 'bg-primary-soft' : 'hover:bg-base-200',
                disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                error ? 'outline-2 outline-error' : '',
            ]"
            :disabled="disabled"
            @dragenter="onDragEnter"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
        >
            <span class="kg-tile kg-tile--lg pointer-events-none">
                <UploadIcon class="size-5.5 text-muted" aria-hidden="true" />
            </span>
            <span class="pointer-events-none text-sm font-medium">{{ t('drop_files_here') }}</span>
            <span class="pointer-events-none text-xs text-muted">{{ t('or_click_to_upload') }}</span>
            <span v-if="maxSizeKb" class="pointer-events-none text-xs text-muted">
                {{ t('max_file_size') }}: {{ formatBytes(maxSizeKb * 1024) }}
            </span>
        </button>
        <input
            :id="`file-input-${endpoint.replace(/\//g, '-')}`"
            type="file"
            class="hidden"
            :multiple="multiple"
            :accept="accept"
            :disabled="disabled"
            tabindex="-1"
            @change="onFileInputChange"
        />

        <p v-if="error" class="text-sm text-error">{{ error }}</p>

        <ul v-if="items.length > 0" class="kg-list">
            <li v-for="item in items" :key="item.id" class="kg-list-row">
                <div class="kg-tile overflow-hidden">
                    <img
                        v-if="item.localUrl && isImage(item.mimeType)"
                        :src="item.localUrl"
                        :alt="item.name"
                        class="size-full object-cover"
                    />
                    <FileIcon v-else class="text-muted" aria-hidden="true" />
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ item.name }}</p>
                    <p class="text-xs text-muted">{{ formatBytes(item.size) }}</p>

                    <div v-if="item.status === 'uploading'" class="mt-1">
                        <progress class="progress progress-primary w-full" :value="item.progress" max="100" />
                    </div>
                    <p v-if="item.status === 'error'" class="mt-1 text-xs text-error">{{ item.error }}</p>
                </div>

                <div class="shrink-0">
                    <span v-if="item.status === 'uploading'" class="loading loading-spinner loading-xs text-primary" />
                    <CheckIcon v-else-if="item.status === 'done'" class="text-primary" aria-hidden="true" />
                    <CloseIcon v-else-if="item.status === 'error'" class="text-error" aria-hidden="true" />
                </div>

                <button
                    type="button"
                    class="btn btn-ghost btn-square"
                    :disabled="disabled"
                    :title="t('remove_file')"
                    :aria-label="t('remove_file')"
                    @click.stop="removeItem(item.id)"
                >
                    <CloseIcon />
                </button>
            </li>
        </ul>
    </div>
</template>
