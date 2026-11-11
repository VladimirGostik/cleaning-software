<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import EyeIcon from '~icons/material-symbols/visibility-outline-rounded';
import DocumentPdfPreviewModal from '@/Components/Documents/DocumentPdfPreviewModal.vue';
import { useDocumentPdfPreview } from '@/Composables/useDocumentPdfPreview';
import { formatBytes } from '@/utils/bytes';
import { getBlob } from '@/utils/http';

const props = defineProps<{
    file: App.Data.Contracts.ContractFileData | null;
}>();

const { t } = useI18n();

const pdf = useDocumentPdfPreview({
    load: (signal) => {
        if (props.file === null) return Promise.reject(new Error('missing file'));

        return getBlob(props.file.download_url, { signal });
    },
});

const isPdf = computed(() => props.file?.mime_type === 'application/pdf');
</script>

<template>
    <div v-if="file === null" class="alert alert-warning" role="alert">
        <span>{{ t('contract_file_no_access') }}</span>
    </div>

    <div v-else class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 p-3">
        <div class="min-w-0">
            <p class="truncate font-medium">{{ file.file_name }}</p>
            <p class="text-xs text-base-content/60">
                {{ file.mime_type ?? t('empty_dash') }} · {{ formatBytes(file.size) }}
            </p>
        </div>

        <div class="flex shrink-0 gap-2">
            <button v-if="isPdf" type="button" class="btn btn-sm btn-neutral" @click="pdf.open()">
                <EyeIcon class="size-5" />
                {{ t('contract_file_preview') }}
            </button>
            <a :href="file.download_url" target="_blank" rel="noopener" class="btn btn-sm btn-neutral">
                <ArrowDownTrayIcon class="size-5" />
                {{ t('uploaded_file_download') }}
            </a>
        </div>
    </div>

    <DocumentPdfPreviewModal
        :is-open="pdf.isOpen.value"
        :pdf-url="pdf.pdfUrl.value"
        :loading="pdf.loading.value"
        :error="pdf.error.value"
        @close="pdf.close"
    />
</template>
