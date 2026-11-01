<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ArrowsPointingInIcon, ArrowsPointingOutIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import DocumentFrameToggles from '@/Components/Documents/DocumentFrameToggles.vue';
import DocumentMissingTokens from '@/Components/Documents/DocumentMissingTokens.vue';
import DocumentPdfPreviewModal from '@/Components/Documents/DocumentPdfPreviewModal.vue';
import DocumentTokenEditor from '@/Components/Documents/DocumentTokenEditor.vue';
import DocumentTokenPicker from '@/Components/Documents/DocumentTokenPicker.vue';
import { usedTokenKeys } from '@/Components/Documents/editor/extensions/templateTokenHtml';
import DocumentTemplateMetaFields from '@/Components/DocumentTemplates/DocumentTemplateMetaFields.vue';
import DocumentTemplatePreview from '@/Components/DocumentTemplates/DocumentTemplatePreview.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import { useDocumentPdfPreview } from '@/Composables/useDocumentPdfPreview';
import { useDocumentTemplatePreview } from '@/Composables/useDocumentTemplatePreview';
import { useFullscreenWorkspace } from '@/Composables/useFullscreenWorkspace';

type TokenDef = App.Data.DocumentTemplates.DocumentTokenData;

interface DocumentTemplateFormData {
    name: string;
    type: App.Enums.DocumentTypeEnum;
    employment_type: string;
    body: string;
    show_header: boolean;
    show_footer: boolean;
    is_active: boolean;
}

const BASE_URL = '/settings/document-templates';

const props = withDefaults(
    defineProps<{
        template?: App.Data.DocumentTemplates.DocumentTemplateEditData | null;
        catalog?: App.Data.DocumentTemplates.DocumentTokenCatalogData | null;
        initialType?: App.Enums.DocumentTypeEnum;
        initialEmploymentType?: App.Enums.EmploymentContractTypeEnum | null;
        isGlobal?: boolean;
        letterhead: App.Data.Documents.DocumentLetterheadData;
        footer: string[];
    }>(),
    {
        template: null,
        catalog: null,
        initialType: 'service_agreement',
        initialEmploymentType: null,
        isGlobal: false,
    },
);

const { t } = useI18n();
const { allows } = useAuthorization();

const readonly = computed(() => props.template !== null && !props.template.can.update);
const typeLocked = computed(() => props.template?.scope === 'override');

const form = useForm<DocumentTemplateFormData>(
    props.template ? 'put' : 'post',
    props.template ? `${BASE_URL}/${props.template.id}` : props.isGlobal ? `${BASE_URL}/global` : BASE_URL,
    {
        name: props.template?.name ?? '',
        type: props.template?.type ?? props.initialType,
        employment_type: props.template?.employment_type ?? props.initialEmploymentType ?? '',
        body: props.template?.body ?? '',
        show_header: props.template?.show_header ?? true,
        show_footer: props.template?.show_footer ?? true,
        is_active: props.template?.is_active ?? true,
    },
);

form.transform((data: DocumentTemplateFormData) => ({
    ...data,
    employment_type: data.type === 'employment' && data.employment_type !== '' ? data.employment_type : null,
}));

const tokens = computed<readonly TokenDef[]>(
    () =>
        props.template?.tokens ??
        props.catalog?.[form.type as keyof App.Data.DocumentTemplates.DocumentTokenCatalogData] ??
        [],
);
const tokensByKey = computed(() => new Map(tokens.value.map((def) => [def.key, def])));

const editLetterhead = computed(() => (form.show_header ? props.letterhead : null));
const editFooter = computed(() => (form.show_footer ? props.footer : []));
const settingsUrl = computed(() => (allows('manage billing settings') ? '/settings/invoicing' : null));

function previewPayload(): App.Data.DocumentTemplates.DocumentTemplatePreviewData {
    return {
        body: form.body,
        type: form.type,
        employment_type:
            form.type === 'employment' && form.employment_type !== ''
                ? (form.employment_type as App.Enums.EmploymentContractTypeEnum)
                : null,
        show_header: form.show_header,
        show_footer: form.show_footer,
    };
}

const mode = ref<'edit' | 'preview'>('edit');
const previewEnabled = computed(() => mode.value === 'preview');
const pickerOpen = ref(false);
const editorRef = ref<InstanceType<typeof DocumentTokenEditor> | null>(null);

const { preview, loading, error } = useDocumentTemplatePreview({ payload: previewPayload, enabled: previewEnabled });

const pdf = useDocumentPdfPreview({ url: `${BASE_URL}/preview-pdf`, payload: previewPayload });

const { fullscreen } = useFullscreenWorkspace(() => {
    if (!pdf.isOpen.value) return false;
    pdf.close();
    return true;
});

const missingTokens = computed(() => {
    const baseBody = props.template?.base_body ?? null;
    if (baseBody === null) return [];
    const used = new Set(usedTokenKeys(form.body));

    return usedTokenKeys(baseBody)
        .filter((key) => !used.has(key))
        .map((key) => ({ key, label: tokensByKey.value.get(key)?.label ?? key }));
});

const missingSummary = computed(() =>
    t('document_missing_summary', { count: missingTokens.value.length }, missingTokens.value.length),
);

function pickToken(def: TokenDef): void {
    editorRef.value?.insertToken(def);
}

function pickMissing(key: string): void {
    const def = tokensByKey.value.get(key);
    if (!def) return;
    mode.value = 'edit';
    void nextTick(() => pickToken(def));
}

function submit(): void {
    form.submit({ preserveScroll: true });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <div v-show="!fullscreen" class="card mb-4 bg-base-100 shadow-sm">
                <div class="card-body">
                    <DocumentTemplateMetaFields
                        :show-employment-type="form.type === 'employment'"
                        :readonly="readonly"
                        :type-locked="typeLocked"
                    />
                    <p v-if="template?.scope === 'override'" class="text-xs text-base-content/60">
                        {{ t('document_template_type_locked_hint') }}
                    </p>
                </div>
            </div>

            <section
                class="flex min-h-0 flex-col gap-3"
                :class="fullscreen ? 'document-workspace--fullscreen' : 'lg:h-[calc(100dvh-18rem)] lg:min-h-[36rem]'"
            >
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <div class="join" role="group" :aria-label="t('document_template_mode_label')">
                        <button
                            type="button"
                            class="btn join-item btn-sm"
                            :class="{ 'btn-active': mode === 'edit' }"
                            :aria-pressed="mode === 'edit'"
                            @click="mode = 'edit'"
                        >
                            {{ t('document_template_mode_text') }}
                        </button>
                        <button
                            type="button"
                            class="btn join-item btn-sm"
                            :class="{ 'btn-active': mode === 'preview' }"
                            :aria-pressed="mode === 'preview'"
                            @click="mode = 'preview'"
                        >
                            {{ t('document_template_preview_title') }}
                        </button>
                    </div>
                    <DocumentFrameToggles
                        v-model:show-header="form.show_header"
                        v-model:show-footer="form.show_footer"
                    />
                    <button
                        v-if="mode === 'edit' && !readonly"
                        type="button"
                        class="btn btn-sm min-[1440px]:hidden"
                        :class="{ 'btn-active': pickerOpen }"
                        :aria-pressed="pickerOpen"
                        @mousedown.prevent
                        @click="pickerOpen = !pickerOpen"
                    >
                        {{ t('document_template_picker_title') }}
                    </button>
                    <button type="button" class="btn btn-sm" :disabled="pdf.loading.value" @click="pdf.open()">
                        {{ t('document_template_preview_pdf') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-square btn-ghost btn-sm"
                        :aria-pressed="fullscreen"
                        :aria-label="
                            fullscreen ? t('document_template_fullscreen_exit') : t('document_template_fullscreen')
                        "
                        :title="fullscreen ? t('document_template_fullscreen_exit') : t('document_template_fullscreen')"
                        @click="fullscreen = !fullscreen"
                    >
                        <ArrowsPointingInIcon v-if="fullscreen" class="size-4" aria-hidden="true" />
                        <ArrowsPointingOutIcon v-else class="size-4" aria-hidden="true" />
                    </button>
                    <span class="flex-1" />
                    <Link v-if="!fullscreen" :href="BASE_URL" class="btn btn-ghost btn-sm">
                        {{ t('back') }}
                    </Link>
                    <button v-if="!readonly" type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('save') }}
                    </button>
                </div>

                <DocumentMissingTokens
                    :tokens="missingTokens"
                    :summary="missingSummary"
                    :pickable="!readonly"
                    @pick="pickMissing"
                />

                <div
                    v-show="mode === 'edit'"
                    class="relative grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-rows-[minmax(0,1fr)]"
                    :class="readonly ? '' : 'min-[1440px]:grid-cols-[minmax(0,1fr)_18rem]'"
                >
                    <div class="card min-h-[32rem] bg-base-100 shadow-sm lg:min-h-0">
                        <div class="flex min-h-0 flex-1 flex-col">
                            <DocumentTokenEditor
                                ref="editorRef"
                                v-model="form.body"
                                :tokens="tokens"
                                :error="form.errors.body ?? null"
                                :readonly="readonly"
                                :letterhead="editLetterhead"
                                :footer="editFooter"
                                :settings-url="settingsUrl"
                            />
                        </div>
                    </div>

                    <aside
                        v-if="!readonly"
                        class="card min-h-0 overflow-y-auto bg-base-100 shadow-sm max-[1439px]:absolute max-[1439px]:inset-y-2 max-[1439px]:right-2 max-[1439px]:z-20 max-[1439px]:w-72 max-[1439px]:shadow-lg"
                        :class="pickerOpen ? '' : 'max-[1439px]:hidden'"
                        :aria-label="t('document_template_picker_title')"
                    >
                        <div class="flex flex-col gap-3 p-6">
                            <h2 class="text-sm font-semibold">{{ t('document_template_picker_title') }}</h2>
                            <DocumentTokenPicker :tokens="tokens" @pick="pickToken" />
                        </div>
                    </aside>
                </div>

                <div v-if="mode === 'preview'" class="card min-h-[32rem] flex-1 bg-base-100 shadow-sm lg:min-h-0">
                    <div class="flex min-h-0 flex-1 flex-col p-6">
                        <DocumentTemplatePreview
                            :preview="preview"
                            :loading="loading"
                            :error="error"
                            :tokens="tokens"
                        />
                    </div>
                </div>
            </section>
        </form>
    </FormProvider>

    <DocumentPdfPreviewModal
        :is-open="pdf.isOpen.value"
        :pdf-url="pdf.pdfUrl.value"
        :loading="pdf.loading.value"
        :error="pdf.error.value"
        @close="pdf.close"
    />
</template>
