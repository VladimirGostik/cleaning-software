<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { addDays } from 'date-fns';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import UploadedFileField from '@/Components/Documents/UploadedFileField.vue';
import QuoteKindSelector from './QuoteKindSelector.vue';
import QuoteSubjectPicker, { type QuoteSubjectMode } from './QuoteSubjectPicker.vue';
import QuoteFormSummary from './QuoteFormSummary.vue';
import QuotePrintCard from './QuotePrintCard.vue';
import QuoteTextDocumentWorkspace from './QuoteTextDocumentWorkspace.vue';
import DocumentPdfPreviewModal from '@/Components/Documents/DocumentPdfPreviewModal.vue';
import InvoiceItemsEditor from '@/Components/Invoices/InvoiceItemsEditor.vue';

import { formatBytes } from '@/utils/bytes';
import { toDateInputValue } from '@/utils/date';
import { toNumber } from '@/utils/money';
import {
    CURRENCIES,
    currencyKey,
    enumOptions,
    QUOTE_BILLING_PERIODS,
    quoteBillingPeriodKey,
    TASK_FREQUENCIES,
    taskFrequencyKey,
} from '@/utils/enums';
import { isRecurringPeriod, quotePeriodTotals } from '@/utils/quoteBilling';
import { quotePayload, type QuoteFormData, type QuoteItemRow } from '@/utils/quoteForm';
import { useQuotePreview } from '@/Composables/useQuotePreview';
import { useDependentValidation } from '@/Composables/useDependentValidation';
import { useQuoteTextDocument } from '@/Composables/useQuoteTextDocument';
import { useAuthorization } from '@/Composables/useAuthorization';

const props = defineProps<{
    context: App.Data.Quotes.QuoteFormContextData;
    quote?: App.Data.Quotes.QuoteDetailData | null;
    prefill?: App.Data.Quotes.QuoteCreatePrefillData | null;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const isEditing = computed(() => !!props.quote);

function blankItem(): QuoteItemRow {
    return {
        id: null,
        description: '',
        frequency: null,
        billing_period: 'one_off',
        note: null,
        quantity: 1,
        unit: null,
        unit_price: 0,
        discount_percent: 0,
        vat_rate: toNumber(props.context.vat_rate),
        line_base: null,
        line_vat: null,
        line_total: null,
    };
}

function initialMode(
    source: Pick<App.Data.Quotes.QuoteDetailData, 'client_id' | 'cleaning_object_id'> | null | undefined,
    isNew: boolean,
): QuoteSubjectMode {
    if (source?.cleaning_object_id) return 'object';
    if (source?.client_id || isNew) return 'client';
    return 'manual';
}

function initialData(): QuoteFormData {
    if (props.quote) {
        const quote = props.quote;
        const isClientless = quote.client_id === null;

        return {
            kind: quote.kind,
            client_id: quote.client_id,
            cleaning_object_id: quote.cleaning_object_id,
            subject: quote.subject,
            number: quote.number,
            issue_date: toDateInputValue(quote.issue_date),
            valid_until: toDateInputValue(quote.valid_until),
            currency: quote.currency,
            note: quote.note,
            included_text: quote.included_text,
            show_totals: quote.show_totals,
            show_vat_note: quote.show_vat_note,
            items: quote.items.map((item) => ({ ...item })),
            customer_name: isClientless ? quote.customer_name : null,
            customer_email: isClientless ? quote.customer_email : null,
            customer_street: isClientless ? quote.customer_street : null,
            customer_city: isClientless ? quote.customer_city : null,
            customer_postal_code: isClientless ? quote.customer_postal_code : null,
            customer_represented_by: isClientless ? quote.customer_represented_by : null,
            customer_ico: isClientless ? quote.customer_ico : null,
            customer_dic: isClientless ? quote.customer_dic : null,
            document_uuid: null,
            document_template_id: quote.text_document?.document_template_id ?? props.context.default_template_id,
            document_body: quote.text_document?.body ?? props.context.default_body,
            document_show_header: quote.text_document?.show_header ?? true,
            document_show_footer: quote.text_document?.show_footer ?? true,
        };
    }

    const today = new Date();
    const kind = props.prefill?.kind ?? 'itemized';

    return {
        kind,
        client_id: props.prefill?.client_id ?? null,
        cleaning_object_id: props.prefill?.cleaning_object_id ?? null,
        subject: null,
        number: null,
        issue_date: toDateInputValue(today),
        valid_until: toDateInputValue(addDays(today, props.context.default_validity_days)),
        currency: props.context.default_currency,
        note: null,
        included_text: props.context.default_included_text,
        show_totals: true,
        show_vat_note: true,
        items: kind === 'document' ? [] : [blankItem()],
        customer_name: null,
        customer_email: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        customer_represented_by: null,
        customer_ico: null,
        customer_dic: null,
        document_uuid: null,
        document_template_id: props.context.default_template_id,
        document_body: props.context.default_body,
        document_show_header: true,
        document_show_footer: true,
    };
}

const form = useForm<QuoteFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/quotes/${props.quote!.id}` : '/quotes',
    initialData(),
);

useDependentValidation(form, {
    // valid_until => after_or_equal:issue_date
    issue_date: ['valid_until'],
    // customer_name => required_without:client_id; cleaning_object_id => ObjectBelongsToClient
    client_id: ['customer_name', 'cleaning_object_id'],
});

form.transform((data: QuoteFormData) => quotePayload(data));

const ui = reactive({
    subjectMode: initialMode(props.quote ?? props.prefill, !props.quote),
});

const textEditorOpen = ref(false);

const templateOptions = computed<App.Data.Quotes.QuoteTemplateOptionData[]>(() => {
    const saved = props.quote?.text_document;
    const known = props.context.templates;

    if (saved?.document_template_id && !known.some((template) => template.id === saved.document_template_id)) {
        return [
            ...known,
            {
                id: saved.document_template_id,
                name: saved.document_template_name ?? t('quote_text_deleted_template'),
                scope: 'firm',
                body: saved.body,
            },
        ];
    }

    return known;
});

const textDocument = useQuoteTextDocument({
    form,
    templates: () => templateOptions.value,
    defaultBody: () => props.context.default_body,
});

const templateName = computed(() => {
    const current = templateOptions.value.find((template) => template.id === form.document_template_id);

    return current?.name ?? props.quote?.text_document?.document_template_name ?? null;
});

const preview = useQuotePreview({
    form: {
        data: () => form.data(),
        setError: (field, message) => form.setError(field as never, message),
        clearErrors: () => form.clearErrors(),
    },
    quoteId: props.quote?.id ?? null,
    incompleteMessage: () => t('quote_preview_incomplete'),
});

const textPreviewError = computed(() => {
    if (preview.textError.value === 'throttled') return t('document_pdf_throttled');
    if (preview.textError.value === 'failed') return t('document_pdf_failed');
    if (preview.textError.value === 'invalid') return t('quote_preview_incomplete');

    return null;
});

async function openTextEditor(): Promise<void> {
    if (await preview.loadTextPreview()) textEditorOpen.value = true;
}
const settingsUrl = computed(() => (allows('manage billing settings') ? '/settings/invoicing' : null));

const periodTotals = computed(() => quotePeriodTotals(form.items, props.context.is_vat_payer));

const hasRecurringPayments = ref(form.items.some((item) => isRecurringPeriod(item.billing_period)));

function setRecurringPayments(enabled: boolean): void {
    hasRecurringPayments.value = enabled;
    if (enabled) return;
    form.items = form.items.map((item) => ({ ...item, billing_period: 'one_off' }));
}

const isDocument = computed(() => form.kind === 'document');
const currencyOptions = computed<SelectOption[]>(() => enumOptions(CURRENCIES, currencyKey, t));
const periodOptions = computed<SelectOption[]>(() => enumOptions(QUOTE_BILLING_PERIODS, quoteBillingPeriodKey, t));
const frequencyOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('quote_item_frequency_none') },
    ...enumOptions(TASK_FREQUENCIES, taskFrequencyKey, t),
]);
const documentAccept = computed(() => props.context.document_allowed_mimes.join(','));
const documentHint = computed(() =>
    t('quote_document_upload_hint', { size: formatBytes(props.context.document_max_size_kb * 1024) }),
);
const canSubmit = computed(() => !(isDocument.value && !isEditing.value && !form.document_uuid));

function setKind(kind: App.Enums.QuoteKindEnum): void {
    form.kind = kind;
    form.document_uuid = null;
    form.document_template_id = props.context.default_template_id;
    form.document_body = props.context.default_body;
    form.document_show_header = true;
    form.document_show_footer = true;
    textDocument.cancelPick();
    form.items = kind === 'document' ? [] : [blankItem()];
}

function updateDate(field: 'issue_date' | 'valid_until', value: string | null): void {
    form[field] = value ?? '';
    callValidate(form, field);
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <div>
                            <div class="[&_input]:text-lg [&_input]:font-medium">
                                <TextInput field="subject" :label="t('quote_subject_text')" />
                            </div>
                            <p class="mt-1 text-xs text-base-content/60">{{ t('quote_subject_hint') }}</p>
                        </div>

                        <QuoteSubjectPicker
                            v-model:mode="ui.subjectMode"
                            :clients="context.clients"
                            :objects="context.objects"
                        >
                            <template #leading>
                                <QuoteKindSelector
                                    :model-value="form.kind"
                                    :locked="isEditing"
                                    :disabled="form.processing"
                                    @update:model-value="setKind"
                                />
                            </template>
                        </QuoteSubjectPicker>
                        <p v-if="form.errors.kind" class="text-sm text-error">{{ form.errors.kind }}</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <DateInput
                                :model-value="form.issue_date"
                                :label="t('quote_issue_date')"
                                required
                                :error="form.errors.issue_date"
                                @update:model-value="updateDate('issue_date', $event)"
                            />

                            <DateInput
                                :model-value="form.valid_until"
                                :label="t('quote_valid_until')"
                                required
                                :error="form.errors.valid_until"
                                @update:model-value="updateDate('valid_until', $event)"
                            />

                            <div>
                                <TextInput field="number" :label="t('quote_number_label')" maxlength="50" />
                                <p class="mt-1 text-xs text-base-content/60">{{ t('quote_number_hint') }}</p>
                            </div>

                            <SelectInput
                                v-if="isDocument"
                                field="currency"
                                :label="t('invoice_currency')"
                                :options="currencyOptions"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="!isDocument" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
                            <h2 class="card-title text-base">{{ t('quote_section_items') }}</h2>
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <ToggleInput
                                        :model-value="hasRecurringPayments"
                                        :label="t('quote_recurring_payments')"
                                        label-class="sr-only"
                                        class="toggle-sm"
                                        @update:model-value="setRecurringPayments"
                                    />
                                    <span aria-hidden="true">{{ t('quote_recurring_payments') }}</span>
                                </div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span aria-hidden="true">{{ t('invoice_currency') }}</span>
                                    <div class="w-28">
                                        <SelectInput
                                            field="currency"
                                            :label="t('invoice_currency')"
                                            label-class="sr-only"
                                            class="select-sm"
                                            :options="currencyOptions"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-base-content/60">{{ t('quote_recurring_payments_hint') }}</p>
                        <InvoiceItemsEditor
                            field="items"
                            :is-vat-payer="context.is_vat_payer"
                            :vat-rate-options="context.vat_rate_options"
                            :currency="form.currency"
                            :blank-row="blankItem"
                            :description-label="t('quote_item_name_label')"
                            :description-placeholder="t('quote_item_name_placeholder')"
                        >
                            <template #row-extra="{ row, index, setField, errors }">
                                <div class="space-y-2">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                        <SelectInput
                                            v-if="hasRecurringPayments"
                                            :model-value="row.billing_period as string"
                                            :label="t('quote_item_billing_period')"
                                            :options="periodOptions"
                                            :error="errors[`items.${index}.billing_period`]"
                                            @update:model-value="setField(index, 'billing_period', $event)"
                                        />
                                        <SelectInput
                                            :model-value="(row.frequency as string | null) ?? ''"
                                            :label="t('quote_item_frequency')"
                                            :options="frequencyOptions"
                                            :error="errors[`items.${index}.frequency`]"
                                            @update:model-value="setField(index, 'frequency', $event || null)"
                                        />
                                    </div>
                                    <TextareaInput
                                        :model-value="(row.note as string | null) ?? ''"
                                        :label="t('quote_item_note')"
                                        :rows="2"
                                        :maxlength="500"
                                        :error="errors[`items.${index}.note`]"
                                        @update:model-value="setField(index, 'note', $event || null)"
                                    />
                                </div>
                            </template>
                        </InvoiceItemsEditor>
                    </div>
                </div>

                <template v-else>
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('quote_section_document') }}</h2>
                            <UploadedFileField
                                v-model="form.document_uuid"
                                :current-file="quote?.document ?? null"
                                :accept="documentAccept"
                                :max-size-kb="context.document_max_size_kb"
                                :hint="documentHint"
                                :error="form.errors.document_uuid"
                            />
                        </div>
                    </div>

                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput
                                :model-value="form.note ?? ''"
                                :label="t('note')"
                                :error="form.errors.note"
                                @update:model-value="form.note = $event"
                            />
                        </div>
                    </div>
                </template>

                <QuotePrintCard
                    v-if="!isDocument"
                    v-model:note="form.note"
                    v-model:included-text="form.included_text"
                    v-model:show-totals="form.show_totals"
                    v-model:show-vat-note="form.show_vat_note"
                    :templates="templateOptions"
                    :template-id="form.document_template_id"
                    :template-name="templateName"
                    :is-edited="textDocument.isEdited.value"
                    :frozen="quote?.text_document?.is_frozen ?? false"
                    :is-vat-payer="context.is_vat_payer"
                    :pending-template-id="textDocument.pendingTemplateId.value"
                    :disabled="form.processing"
                    :loading="preview.textLoading.value"
                    :error="form.errors.document_template_id ?? form.errors.document_body ?? textPreviewError"
                    :note-error="form.errors.note"
                    :included-error="form.errors.included_text"
                    @pick="textDocument.pickTemplate"
                    @confirm-pick="textDocument.confirmPick"
                    @cancel-pick="textDocument.cancelPick"
                    @edit="openTextEditor"
                    @preview="preview.pdf.open"
                />

                <FormActions
                    :cancel-href="isEditing ? `/quotes/${quote!.id}` : '/quotes'"
                    :submit-label="isEditing ? t('save') : t('quote_add')"
                    :processing="form.processing"
                    :disabled="!canSubmit"
                />
            </div>

            <div>
                <QuoteFormSummary
                    class="lg:sticky lg:top-4"
                    :number="quote?.number ?? null"
                    :kind="form.kind"
                    :issue-date="form.issue_date"
                    :valid-until="form.valid_until"
                    :currency="form.currency"
                    :is-vat-payer="context.is_vat_payer"
                    :period-totals="periodTotals"
                    :show-totals="form.show_totals"
                    :preview-loading="preview.pdf.loading.value"
                    @preview="preview.pdf.open"
                />
            </div>
        </form>

        <QuoteTextDocumentWorkspace
            v-if="textEditorOpen && form.document_body !== null"
            v-model:body="form.document_body"
            v-model:show-header="form.document_show_header"
            v-model:show-footer="form.document_show_footer"
            :tokens="context.tokens"
            :letterhead="context.letterhead"
            :footer="context.footer"
            :error="form.errors.document_body"
            :settings-url="settingsUrl"
            :preview="preview.textPreview.value"
            @close="textEditorOpen = false"
        />

        <DocumentPdfPreviewModal
            :is-open="preview.pdf.isOpen.value"
            :pdf-url="preview.pdf.pdfUrl.value"
            :loading="preview.pdf.loading.value"
            :error="preview.pdf.error.value"
            @close="preview.pdf.close"
        />
    </FormProvider>
</template>
