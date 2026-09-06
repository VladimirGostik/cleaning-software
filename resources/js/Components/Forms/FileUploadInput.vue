<script setup lang="ts">
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

// Populate items from initialFiles on mount
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
        return t('app.file_too_large', { max: formatBytes(props.maxSizeKb * 1024) });
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
            return t('app.file_type_not_allowed');
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
            item.error = t('app.upload_failed');
        }
    });
    xhr.addEventListener('error', () => {
        item.status = 'error';
        item.error = t('app.upload_failed');
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
    if (arr.length > remaining) {
        // silently truncate — could also show a toast
    }

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
        // keep only last item
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
        // Fire-and-forget DELETE to server if uuid exists
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

// Sync external modelValue reset (e.g. form.reset())
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
        <!-- Drop zone -->
        <div
            class="border-2 border-dashed rounded-box p-6 text-center transition-colors cursor-pointer"
            :class="[
                isDragging.value ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-primary/50',
                disabled ? 'opacity-50 cursor-not-allowed' : '',
                error ? 'border-error' : '',
            ]"
            @dragenter="onDragEnter"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
        >
            <input
                :id="`file-input-${endpoint.replace(/\//g, '-')}`"
                type="file"
                class="hidden"
                :multiple="multiple"
                :accept="accept"
                :disabled="disabled"
                @change="onFileInputChange"
            />
            <div class="flex flex-col items-center gap-1 pointer-events-none">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-10 w-10 text-base-content/30"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                    />
                </svg>
                <p class="text-sm font-medium text-base-content">{{ t('app.drop_files_here') }}</p>
                <p class="text-xs text-base-content/50">{{ t('app.or_click_to_upload') }}</p>
                <p v-if="maxSizeKb" class="text-xs text-base-content/40">
                    {{ t('app.max_file_size') }}: {{ formatBytes(maxSizeKb * 1024) }}
                </p>
            </div>
        </div>

        <!-- Error from parent -->
        <p v-if="error" class="text-error text-sm">{{ error }}</p>

        <!-- File list -->
        <ul v-if="items.length > 0" class="space-y-2">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex items-center gap-3 p-3 rounded-box border border-base-200 bg-base-50"
            >
                <!-- Thumbnail -->
                <div
                    class="w-12 h-12 rounded flex-shrink-0 bg-base-200 overflow-hidden flex items-center justify-center"
                >
                    <img
                        v-if="item.localUrl && isImage(item.mimeType)"
                        :src="item.localUrl"
                        :alt="item.name"
                        class="w-full h-full object-cover"
                    />
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-6 w-6 text-base-content/30"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                        />
                    </svg>
                </div>

                <!-- Info + progress -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ item.name }}</p>
                    <p class="text-xs text-base-content/50">{{ formatBytes(item.size) }}</p>

                    <div v-if="item.status === 'uploading'" class="mt-1">
                        <progress class="progress progress-primary w-full h-1" :value="item.progress" max="100" />
                    </div>
                    <p v-if="item.status === 'error'" class="text-xs text-error mt-1">{{ item.error }}</p>
                </div>

                <!-- Status badge -->
                <div class="flex-shrink-0">
                    <span v-if="item.status === 'uploading'" class="loading loading-spinner loading-xs text-primary" />
                    <svg
                        v-else-if="item.status === 'done'"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-success"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg
                        v-else-if="item.status === 'error'"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-error"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </div>

                <!-- Remove button -->
                <button
                    type="button"
                    class="btn btn-ghost btn-xs btn-circle"
                    :disabled="disabled"
                    :title="t('app.remove_file')"
                    @click.stop="removeItem(item.id)"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </li>
        </ul>
    </div>
</template>
