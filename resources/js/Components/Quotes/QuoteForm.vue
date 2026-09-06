<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { addDays } from 'date-fns';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import QuoteKindSelector from './QuoteKindSelector.vue';
import QuoteSubjectPicker, { type QuoteSubjectMode } from './QuoteSubjectPicker.vue';
import QuoteDocumentUpload from './QuoteDocumentUpload.vue';
import QuoteFormSummary from './QuoteFormSummary.vue';
import InvoiceItemsEditor, { type ItemRow } from '@/Components/Invoices/InvoiceItemsEditor.vue';

import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { toDateInputValue } from '@/utils/date';
import { toNumber } from '@/utils/money';
import { CURRENCIES, currencyKey, enumOptions } from '@/utils/enums';

type QuoteItemRow = ItemRow & {
    id: string | null;
    frequency: string | null;
    note: string | null;
    line_base: number | null;
    line_vat: number | null;
    line_total: number | null;
};

interface QuoteFormData {
    kind: App.Enums.QuoteKindEnum;
    client_id: string | null;
    cleaning_object_id: string | null;
    subject: string | null;
    number: string | null;
    issue_date: string;
    valid_until: string;
    currency: App.Enums.CurrencyEnum;
    note: string | null;
    items: QuoteItemRow[];
    customer_name: string | null;
    customer_email: string | null;
    customer_street: string | null;
    customer_city: string | null;
    customer_postal_code: string | null;
    document_uuid: string | null;
}

const props = defineProps<{
    context: App.Data.Quotes.QuoteFormContextData;
    quote?: App.Data.Quotes.QuoteDetailData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.quote);

function blankItem(): QuoteItemRow {
    return {
        id: null,
        description: '',
        frequency: null,
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

function initialMode(quote: App.Data.Quotes.QuoteDetailData | null | undefined): QuoteSubjectMode {
    if (!quote) return 'client';
    if (quote.cleaning_object_id) return 'object';
    if (quote.client_id) return 'client';
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
            items: quote.items.map((item) => ({ ...item })),
            customer_name: isClientless ? quote.customer_name : null,
            customer_email: isClientless ? quote.customer_email : null,
            customer_street: isClientless ? quote.customer_street : null,
            customer_city: isClientless ? quote.customer_city : null,
            customer_postal_code: isClientless ? quote.customer_postal_code : null,
            document_uuid: null,
        };
    }

    const today = new Date();

    return {
        kind: 'itemized',
        client_id: null,
        cleaning_object_id: null,
        subject: null,
        number: null,
        issue_date: toDateInputValue(today),
        valid_until: toDateInputValue(addDays(today, props.context.default_validity_days)),
        currency: props.context.default_currency,
        note: null,
        items: [blankItem()],
        customer_name: null,
        customer_email: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        document_uuid: null,
    };
}

const form = useForm<QuoteFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/quotes/${props.quote!.id}` : '/quotes',
    initialData(),
);

form.transform((data: QuoteFormData) => {
    const { items, document_uuid, ...rest } = data;

    return {
        ...rest,
        subject: rest.subject || null,
        number: rest.number || null,
        note: rest.note || null,
        ...(data.kind === 'document' ? { document_uuid } : { items }),
    };
});

const ui = reactive({
    subjectMode: initialMode(props.quote),
});

const totals = useInvoiceTotals(
    () => form.items,
    () => props.context.is_vat_payer,
    () => 0,
    () => 'none',
);

const isDocument = computed(() => form.kind === 'document');
const currencyOptions = computed<SelectOption[]>(() => enumOptions(CURRENCIES, currencyKey, t));
const documentAccept = computed(() => props.context.document_allowed_mimes.join(','));
const canSubmit = computed(() => !(isDocument.value && !isEditing.value && !form.document_uuid));

function setKind(kind: App.Enums.QuoteKindEnum): void {
    form.kind = kind;
    form.document_uuid = null;
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
        <form novalidate class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]" @submit.prevent="submit">
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <QuoteKindSelector
                            :model-value="form.kind"
                            :locked="isEditing"
                            :disabled="form.processing"
                            @update:model-value="setKind"
                        />
                        <p v-if="form.errors.kind" class="mt-2 text-sm text-error">{{ form.errors.kind }}</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('quote_section_subject') }}</h2>
                        <QuoteSubjectPicker
                            v-model:mode="ui.subjectMode"
                            :clients="context.clients"
                            :objects="context.objects"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('quote_section_details') }}</h2>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <TextInput field="number" :label="t('quote_number_label')" maxlength="50" />
                                <p class="mt-1 text-xs text-base-content/60">{{ t('quote_number_hint') }}</p>
                            </div>

                            <TextInput field="subject" :label="t('quote_subject_text')" />

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

                            <SelectInput field="currency" :label="t('invoice_currency')" :options="currencyOptions" />
                        </div>
                    </div>
                </div>

                <div v-if="!isDocument" class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('quote_section_items') }}</h2>
                        <InvoiceItemsEditor
                            field="items"
                            :is-vat-payer="context.is_vat_payer"
                            :vat-rate-options="context.vat_rate_options"
                            :currency="form.currency"
                            :blank-row="blankItem"
                        >
                            <template #row-extra="{ row, index, setField, errors }">
                                <div class="space-y-2">
                                    <TextInput
                                        :model-value="(row.frequency as string | null) ?? ''"
                                        :label="t('quote_item_frequency')"
                                        :placeholder="t('quote_item_frequency_placeholder')"
                                        maxlength="50"
                                        :error="errors[`items.${index}.frequency`]"
                                        @update:model-value="setField(index, 'frequency', $event || null)"
                                    />
                                    <TextInput
                                        :model-value="(row.note as string | null) ?? ''"
                                        :label="t('quote_item_note')"
                                        maxlength="500"
                                        :error="errors[`items.${index}.note`]"
                                        @update:model-value="setField(index, 'note', $event || null)"
                                    />
                                </div>
                            </template>
                        </InvoiceItemsEditor>
                    </div>
                </div>

                <div v-else class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('quote_section_document') }}</h2>
                        <QuoteDocumentUpload
                            v-model="form.document_uuid"
                            :current-document="quote?.document ?? null"
                            :accept="documentAccept"
                            :max-size-kb="context.document_max_size_kb"
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
                    :subtotal="totals.subtotal.value"
                    :vat-amount="totals.vatAmount.value"
                    :vat-breakdown="totals.vatBreakdown.value"
                    :total="totals.total.value"
                />
            </div>
        </form>
    </FormProvider>
</template>
