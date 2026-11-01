<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentTextIcon from '~icons/material-symbols/description-outline-rounded';

import DocumentFrameToggles from '@/Components/Documents/DocumentFrameToggles.vue';
import DocumentMissingTokens from '@/Components/Documents/DocumentMissingTokens.vue';
import DocumentPdfPreviewModal from '@/Components/Documents/DocumentPdfPreviewModal.vue';
import DocumentTokenEditor from '@/Components/Documents/DocumentTokenEditor.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import { useDocumentPdfPreview } from '@/Composables/useDocumentPdfPreview';
import { GENERATE_PDF_URL } from '@/Composables/useContractDocument';

const props = defineProps<{
    tokens: readonly App.Data.DocumentTemplates.DocumentTokenData[];
    missing: { key: string; label: string }[];
    letterhead: App.Data.Documents.DocumentLetterheadData;
    footer: string[];
    bodyError: string | null;
    pdfPayload: () => App.Data.Contracts.ContractGenerateData;
    processing: boolean;
    submitLabel: string;
}>();

const emit = defineEmits<{
    back: [];
}>();

const body = defineModel<string>('body', { required: true });
const showHeader = defineModel<boolean>('showHeader', { required: true });
const showFooter = defineModel<boolean>('showFooter', { required: true });

const { t } = useI18n();
const { allows } = useAuthorization();

const pdf = useDocumentPdfPreview({ url: GENERATE_PDF_URL, payload: () => props.pdfPayload() });

const settingsUrl = computed(() => (allows('manage billing settings') ? '/settings/invoicing' : null));
const missingSummary = computed(() =>
    t('document_missing_summary', { count: props.missing.length }, props.missing.length),
);
</script>

<template>
    <div class="space-y-4">
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="card-title text-base">{{ t('contract_section_body') }}</h2>
                    <div class="flex flex-wrap items-center gap-3">
                        <DocumentFrameToggles v-model:show-header="showHeader" v-model:show-footer="showFooter" />
                        <button type="button" class="btn btn-sm" @click="pdf.open">
                            <DocumentTextIcon class="size-5" aria-hidden="true" />
                            {{ t('contract_step_pdf_preview') }}
                        </button>
                    </div>
                </div>

                <p class="text-sm text-base-content/60">{{ t('contract_step_document_hint') }}</p>

                <DocumentMissingTokens :tokens="missing" :summary="missingSummary" />

                <div class="h-[70dvh] min-h-96">
                    <DocumentTokenEditor
                        v-model="body"
                        :tokens="tokens"
                        :error="bodyError"
                        :aria-label="t('contract_body')"
                        :letterhead="showHeader ? letterhead : null"
                        :footer="showFooter ? footer : []"
                        :settings-url="settingsUrl"
                    />
                </div>
            </div>
        </div>

        <FormActions
            :cancel-label="t('contract_step_back')"
            :submit-label="submitLabel"
            :processing="processing"
            @cancel="emit('back')"
        />

        <DocumentPdfPreviewModal
            :is-open="pdf.isOpen.value"
            :pdf-url="pdf.pdfUrl.value"
            :loading="pdf.loading.value"
            :error="pdf.error.value"
            @close="pdf.close"
        />
    </div>
</template>
