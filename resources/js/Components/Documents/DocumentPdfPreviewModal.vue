<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { PdfPreviewError } from '@/Composables/useDocumentPdfPreview';

defineProps<{
    isOpen: boolean;
    pdfUrl: string | null;
    loading: boolean;
    error: PdfPreviewError | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': isOpen }" :aria-label="t('document_pdf_title')">
        <div class="modal-box flex h-[90dvh] w-11/12 max-w-6xl flex-col gap-3">
            <div class="flex shrink-0 items-center justify-between gap-3">
                <h3 class="text-lg font-bold">{{ t('document_pdf_title') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm" @click="emit('close')">
                    {{ t('close') }}
                </button>
            </div>

            <div v-if="loading" class="flex flex-1 items-center justify-center">
                <span class="loading loading-spinner loading-lg" role="status" :aria-label="t('loading')" />
            </div>
            <p v-else-if="error" class="text-error" role="alert">
                {{ error.message ?? t(error.kind === 'throttled' ? 'document_pdf_throttled' : 'document_pdf_failed') }}
            </p>
            <iframe
                v-else-if="pdfUrl"
                :src="pdfUrl"
                :title="t('document_pdf_title')"
                class="min-h-0 w-full flex-1 rounded-box border border-base-300"
            />
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">{{ t('close') }}</button>
        </form>
    </dialog>
</template>
