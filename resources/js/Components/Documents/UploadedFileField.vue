<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import FormField from '@/Components/Forms/FormField.vue';
import FileUploadInput from '@/Components/Forms/FileUploadInput.vue';
import { formatBytes } from '@/utils/bytes';

interface CurrentFile {
    file_name: string;
    mime_type: string | null;
    size: number;
    download_url: string;
}

const props = defineProps<{
    modelValue: string | null;
    currentFile: CurrentFile | null;
    accept: string;
    maxSizeKb: number;
    hint: string;
    error?: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();

const uploadLabel = computed(() =>
    props.currentFile ? t('uploaded_file_replace_label') : t('uploaded_file_upload_label'),
);

function onUpdate(value: string | string[] | null): void {
    emit('update:modelValue', Array.isArray(value) ? (value[0] ?? null) : value);
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="props.currentFile"
            class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-3"
        >
            <div class="min-w-0">
                <p class="truncate font-medium">{{ props.currentFile.file_name }}</p>
                <p class="text-xs text-base-content/60">
                    {{ props.currentFile.mime_type ?? t('empty_dash') }} ·
                    {{ formatBytes(props.currentFile.size) }}
                </p>
            </div>
            <a :href="props.currentFile.download_url" target="_blank" rel="noopener" class="btn btn-sm shrink-0">
                {{ t('uploaded_file_download') }}
            </a>
        </div>

        <FormField :label="uploadLabel" :error="props.error" :required="!props.currentFile">
            <FileUploadInput
                :model-value="props.modelValue"
                :accept="props.accept"
                :max-size-kb="props.maxSizeKb"
                endpoint="/uploads"
                @update:model-value="onUpdate"
            />
        </FormField>

        <p class="text-xs text-base-content/60">{{ props.hint }}</p>
    </div>
</template>
