<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import FormField from '@/Components/Forms/FormField.vue';
import FileUploadInput from '@/Components/Forms/FileUploadInput.vue';
import { formatBytes } from '@/utils/bytes';

const props = defineProps<{
    modelValue: string | null;
    currentDocument: App.Data.MediaFileData | null;
    accept: string;
    maxSizeKb: number;
    error: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
}>();

const { t } = useI18n();

const uploadLabel = computed(() =>
    props.currentDocument ? t('quote_document_replace_label') : t('quote_document_upload_label'),
);

function onUpdate(value: string | string[] | null): void {
    emit('update:modelValue', Array.isArray(value) ? (value[0] ?? null) : value);
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="props.currentDocument"
            class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-3"
        >
            <div class="min-w-0">
                <p class="truncate font-medium">{{ props.currentDocument.file_name }}</p>
                <p class="text-xs text-base-content/60">
                    {{ props.currentDocument.mime_type ?? t('empty_dash') }} ·
                    {{ formatBytes(props.currentDocument.size) }}
                </p>
            </div>
            <a :href="props.currentDocument.download_url" target="_blank" rel="noopener" class="btn btn-sm shrink-0">
                {{ t('quote_document_download') }}
            </a>
        </div>

        <FormField :label="uploadLabel" :error="props.error" :required="!props.currentDocument">
            <FileUploadInput
                :model-value="props.modelValue"
                :accept="props.accept"
                :max-size-kb="props.maxSizeKb"
                endpoint="/uploads"
                @update:model-value="onUpdate"
            />
        </FormField>

        <p class="text-xs text-base-content/60">
            {{ t('quote_document_upload_hint', { size: formatBytes(props.maxSizeKb * 1024) }) }}
        </p>
    </div>
</template>
