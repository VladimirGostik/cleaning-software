<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { addDays } from 'date-fns';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import InvoiceSubjectPicker, { type SubjectMode } from './InvoiceSubjectPicker.vue';
import InvoiceItemsEditor, { type ItemRow } from './InvoiceItemsEditor.vue';
import InvoiceFormSummary from './InvoiceFormSummary.vue';
import InvoicePaymentAdvancedFields from './InvoicePaymentAdvancedFields.vue';

import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { toDateInputValue } from '@/utils/date';
import { toNumber } from '@/utils/money';
import { useDependentValidation } from '@/Composables/useDependentValidation';
import {
    enumOptions,
    INVOICE_TEMPLATES,
    INVOICE_TYPES,
    invoiceTemplateKey,
    invoiceTypeKey,
    paymentTypeKey,
    roundingModeKey,
} from '@/utils/enums';

type InvoiceItemRow = ItemRow & {
    id: string | null;
    line_base: number | null;
    line_vat: number | null;
    line_total: number | null;
};

interface InvoiceFormData {
    client_id: string | null;
    cleaning_object_id: string | null;
    type: App.Enums.InvoiceTypeEnum;
    template: App.Enums.InvoiceTemplateEnum | '';
    issue_date: string;
    delivery_date: string;
    due_date: string;
    period_from: string | null;
    period_to: string | null;
    customer_name: string | null;
    customer_representative: string | null;
    customer_ico: string | null;
    customer_dic: string | null;
    customer_vat_number: string | null;
    customer_street: string | null;
    customer_city: string | null;
    customer_postal_code: string | null;
    customer_country: string | null;
    customer_email: string | null;
    note: string | null;
    items: InvoiceItemRow[];
    constant_symbol: string | null;
    specific_symbol: string | null;
    header_text: string | null;
    footer_text: string | null;
    deposit: number;
    payment_type: App.Enums.PaymentTypeEnum;
    currency: App.Enums.CurrencyEnum;
    rounding_mode: App.Enums.RoundingModeEnum;
}

const props = defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
    invoice?: App.Data.Invoices.InvoiceDetailData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.invoice);

function blankItem(): InvoiceItemRow {
    return {
        id: null,
        description: '',
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

function initialMode(invoice: App.Data.Invoices.InvoiceDetailData | null | undefined): SubjectMode {
    if (!invoice) return 'standalone';
    if (invoice.cleaning_object_id) return 'object';
    if (invoice.client_id) return 'client';
    return 'standalone';
}

function initialData(): InvoiceFormData {
    if (props.invoice) {
        const invoice = props.invoice;
        return {
            client_id: invoice.client_id,
            cleaning_object_id: invoice.cleaning_object_id,
            type: invoice.type,
            template: invoice.template,
            issue_date: toDateInputValue(invoice.issue_date),
            delivery_date: toDateInputValue(invoice.delivery_date),
            due_date: toDateInputValue(invoice.due_date),
            period_from: invoice.period_from,
            period_to: invoice.period_to,
            customer_name: invoice.customer_name,
            customer_representative: invoice.customer_representative,
            customer_ico: invoice.customer_ico,
            customer_dic: invoice.customer_dic,
            customer_vat_number: invoice.customer_vat_number,
            customer_street: invoice.customer_street,
            customer_city: invoice.customer_city,
            customer_postal_code: invoice.customer_postal_code,
            customer_country: invoice.customer_country,
            customer_email: invoice.customer_email,
            note: invoice.note,
            items: invoice.items.map((item) => ({ ...item })),
            constant_symbol: invoice.constant_symbol,
            specific_symbol: invoice.specific_symbol,
            header_text: invoice.header_text,
            footer_text: invoice.footer_text,
            deposit: toNumber(invoice.deposit),
            payment_type: invoice.payment_type,
            currency: invoice.currency,
            rounding_mode: invoice.rounding_mode,
        };
    }

    const today = new Date();

    return {
        client_id: null,
        cleaning_object_id: null,
        type: 'one_off',
        template: '',
        issue_date: toDateInputValue(today),
        delivery_date: toDateInputValue(today),
        due_date: toDateInputValue(addDays(today, 14)),
        period_from: null,
        period_to: null,
        customer_name: null,
        customer_representative: null,
        customer_ico: null,
        customer_dic: null,
        customer_vat_number: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        customer_country: 'SK',
        customer_email: null,
        note: null,
        items: [blankItem()],
        constant_symbol: props.context.defaults.constant_symbol,
        specific_symbol: null,
        header_text: props.context.defaults.header_text,
        footer_text: props.context.defaults.footer_text,
        deposit: 0,
        payment_type: props.context.defaults.payment_type,
        currency: props.context.defaults.currency,
        rounding_mode: props.context.defaults.rounding_mode,
    };
}

const form = useForm<InvoiceFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/invoices/${props.invoice!.id}` : '/invoices',
    initialData(),
);

form.transform((data: InvoiceFormData) => ({
    ...data,
    template: data.template || null,
    header_text: data.header_text || null,
    footer_text: data.footer_text || null,
    note: data.note || null,
}));

useDependentValidation(form, {
    // due_date => after_or_equal:issue_date
    issue_date: ['due_date'],
    // period_to => after_or_equal:period_from
    period_from: ['period_to'],
    // period_from/period_to => required_if:type,monthly,special
    type: ['period_from', 'period_to'],
    // customer_name => required_without:client_id; cleaning_object_id => ObjectBelongsToClient
    client_id: ['customer_name', 'cleaning_object_id'],
});

const ADVANCED_FIELDS = ['payment_type', 'currency', 'rounding_mode', 'constant_symbol', 'specific_symbol'] as const;

const hasPaymentOverride = computed<boolean>(() => {
    const d = props.context.defaults;
    return (
        form.payment_type !== d.payment_type ||
        form.currency !== d.currency ||
        form.rounding_mode !== d.rounding_mode ||
        (form.constant_symbol ?? '').trim() !== (d.constant_symbol ?? '').trim() ||
        (form.specific_symbol ?? '').trim() !== ''
    );
});

const hasAdvancedError = computed<boolean>(() => ADVANCED_FIELDS.some((f) => !!form.errors[f]));

const advancedSummary = computed<string>(() =>
    [
        t(paymentTypeKey(form.payment_type)),
        form.currency,
        t(roundingModeKey(form.rounding_mode)),
        form.constant_symbol ? `${t('invoice_pdf_constant_symbol')} ${form.constant_symbol}` : null,
    ]
        .filter((v): v is string => v !== null)
        .join(' · '),
);

const ui = reactive({
    subjectMode: initialMode(props.invoice),
    // Covers both: Edit where the stored value already differs from the tenant default,
    // and any error already present in `form.errors` at mount (e.g. server-flashed
    // validation on first render).
    advancedOpen: hasPaymentOverride.value || hasAdvancedError.value,
});

// Reopens on later false->true transitions; mount-time case is covered by the initializer above.
watch(hasAdvancedError, (v) => {
    if (v) ui.advancedOpen = true;
});

const totals = useInvoiceTotals(
    () => form.items,
    () => props.context.is_vat_payer,
    () => form.deposit,
    () => form.rounding_mode,
);

const typeOptions = computed<RadioOption[]>(() => enumOptions(INVOICE_TYPES, invoiceTypeKey, t));
const templateOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('invoice_template_default') },
    ...enumOptions(INVOICE_TEMPLATES, invoiceTemplateKey, t),
]);

function updateRequiredDate(field: 'issue_date' | 'delivery_date' | 'due_date', value: string | null): void {
    form[field] = value ?? '';
    callValidate(form, field);
}

function updatePeriodDate(field: 'period_from' | 'period_to', value: string | null): void {
    form[field] = value;
    callValidate(form, field);
}

function submit(): void {
    // onError, not the watcher: a repeated same-error submit produces no false->true edge.
    form.submit({
        onError: () => {
            if (hasAdvancedError.value) {
                ui.advancedOpen = true;
            }
        },
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]" @submit.prevent="submit">
            <div class="space-y-6">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoice_section_subject') }}</h2>
                        <InvoiceSubjectPicker
                            v-model:mode="ui.subjectMode"
                            :clients="context.clients"
                            :objects="context.objects"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_details') }}</h2>

                        <RadioGroup field="type" :label="t('type')" :options="typeOptions" />

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <SelectInput field="template" :label="t('invoice_template')" :options="templateOptions" />

                            <DateInput
                                :model-value="form.issue_date"
                                :label="t('invoice_pdf_issue_date')"
                                required
                                :error="form.errors.issue_date"
                                @update:model-value="updateRequiredDate('issue_date', $event)"
                            />

                            <DateInput
                                :model-value="form.delivery_date"
                                :label="t('invoice_pdf_delivery_date')"
                                required
                                :error="form.errors.delivery_date"
                                @update:model-value="updateRequiredDate('delivery_date', $event)"
                            />

                            <DateInput
                                :model-value="form.due_date"
                                :label="t('invoice_pdf_due_date')"
                                required
                                :error="form.errors.due_date"
                                @update:model-value="updateRequiredDate('due_date', $event)"
                            />

                            <template v-if="form.type !== 'one_off'">
                                <DateInput
                                    :model-value="form.period_from"
                                    :label="t('invoice_period_from')"
                                    :error="form.errors.period_from"
                                    @update:model-value="updatePeriodDate('period_from', $event)"
                                />

                                <DateInput
                                    :model-value="form.period_to"
                                    :label="t('invoice_period_to')"
                                    :error="form.errors.period_to"
                                    @update:model-value="updatePeriodDate('period_to', $event)"
                                />
                            </template>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_payment') }}</h2>

                        <NumberInput
                            :model-value="form.deposit"
                            :label="t('invoice_pdf_deposit')"
                            :min="0"
                            :step="0.01"
                            :error="form.errors.deposit"
                            @update:model-value="
                                form.deposit = $event ?? 0;
                                callValidate(form, 'deposit');
                            "
                        />

                        <InvoicePaymentAdvancedFields
                            v-model:open="ui.advancedOpen"
                            :has-override="hasPaymentOverride"
                            :has-error="hasAdvancedError"
                            :summary="advancedSummary"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_items') }}</h2>

                        <TextareaInput
                            :model-value="form.header_text ?? ''"
                            :label="t('invoice_header_text')"
                            :rows="2"
                            :error="form.errors.header_text"
                            @update:model-value="form.header_text = $event"
                        />

                        <InvoiceItemsEditor
                            field="items"
                            :is-vat-payer="context.is_vat_payer"
                            :vat-rate-options="context.vat_rate_options"
                            :currency="form.currency"
                            :blank-row="blankItem"
                        />

                        <TextareaInput
                            :model-value="form.footer_text ?? ''"
                            :label="t('invoice_footer_text')"
                            :rows="2"
                            :error="form.errors.footer_text"
                            @update:model-value="form.footer_text = $event"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('note') }}</h2>

                        <TextareaInput
                            :model-value="form.note ?? ''"
                            :label="t('note')"
                            :error="form.errors.note"
                            @update:model-value="form.note = $event"
                        />
                    </div>
                </div>

                <FormActions
                    :cancel-href="isEditing ? `/invoices/${props.invoice!.id}` : '/invoices'"
                    :submit-label="isEditing ? t('save') : t('invoice_add')"
                    :processing="form.processing"
                />
            </div>

            <div>
                <InvoiceFormSummary
                    class="lg:sticky lg:top-4"
                    :number="props.invoice?.number ?? null"
                    :type="form.type"
                    :issue-date="form.issue_date"
                    :due-date="form.due_date"
                    :currency="form.currency"
                    :is-vat-payer="context.is_vat_payer"
                    :subtotal="totals.subtotal.value"
                    :vat-amount="totals.vatAmount.value"
                    :vat-breakdown="totals.vatBreakdown.value"
                    :rounding-amount="totals.roundingAmount.value"
                    :total="totals.total.value"
                    :deposit="form.deposit"
                    :balance-due="totals.balanceDue.value"
                />
            </div>
        </form>
    </FormProvider>
</template>
