<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { addDays, parseISO } from 'date-fns';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import InvoiceSubjectPicker, { type SubjectMode } from '@/Components/Invoices/InvoiceSubjectPicker.vue';
import InvoiceItemsEditor, { type ItemRow } from '@/Components/Invoices/InvoiceItemsEditor.vue';
import InvoiceFormSummary from '@/Components/Invoices/InvoiceFormSummary.vue';

import { useInvoiceTotals } from '@/Composables/useInvoiceTotals';
import { toDateInputValue } from '@/utils/date';
import { toNumber } from '@/utils/money';
import {
    CURRENCIES,
    currencyKey,
    enumOptions,
    INVOICE_TEMPLATES,
    INVOICE_TYPES,
    invoiceTemplateKey,
    invoiceTypeKey,
    PAYMENT_TYPES,
    paymentTypeKey,
    RECURRING_FREQUENCIES,
    recurringDefaultStateKey,
    recurringFrequencyKey,
    ROUNDING_MODES,
    roundingModeKey,
} from '@/utils/enums';

type EndMode = 'forever' | 'until_date' | 'count';

interface RecurringInvoiceFormData {
    client_id: string | null;
    cleaning_object_id: string | null;
    name: string;
    type: App.Enums.InvoiceTypeEnum;
    template: App.Enums.InvoiceTemplateEnum | '';
    frequency: App.Enums.RecurringFrequencyEnum;
    day_of_month: number;
    auto_issue: boolean;
    start_date: string;
    end_date: string | null;
    occurrences_limit: number | null;
    due_days: number;
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
    items: ItemRow[];
    constant_symbol: string | null;
    header_text: string | null;
    footer_text: string | null;
    deposit: number;
    payment_type: App.Enums.PaymentTypeEnum;
    currency: App.Enums.CurrencyEnum;
    rounding_mode: App.Enums.RoundingModeEnum;
}

const props = defineProps<{
    context: App.Data.Invoices.InvoiceFormContextData;
    recurringInvoice?: App.Data.RecurringInvoices.RecurringInvoiceDetailData | null;
}>();

const { t } = useI18n();

const isEditing = computed(() => !!props.recurringInvoice);

function blankItem(): ItemRow {
    return {
        description: '',
        quantity: 1,
        unit: null,
        unit_price: 0,
        discount_percent: 0,
        vat_rate: toNumber(props.context.vat_rate),
    };
}

function initialMode(ri: App.Data.RecurringInvoices.RecurringInvoiceDetailData | null | undefined): SubjectMode {
    if (!ri) return 'standalone';
    if (ri.cleaning_object_id) return 'object';
    if (ri.client_id) return 'client';
    return 'standalone';
}

function initialEndMode(ri: App.Data.RecurringInvoices.RecurringInvoiceDetailData | null | undefined): EndMode {
    if (!ri) return 'forever';
    if (ri.end_date) return 'until_date';
    if (ri.occurrences_limit !== null) return 'count';
    return 'forever';
}

function initialData(): RecurringInvoiceFormData {
    if (props.recurringInvoice) {
        const ri = props.recurringInvoice;
        return {
            client_id: ri.client_id,
            cleaning_object_id: ri.cleaning_object_id,
            name: ri.name,
            type: ri.type,
            template: ri.template ?? '',
            frequency: ri.frequency,
            day_of_month: ri.day_of_month,
            auto_issue: ri.auto_issue,
            start_date: toDateInputValue(ri.start_date),
            end_date: ri.end_date,
            occurrences_limit: ri.occurrences_limit,
            due_days: ri.due_days,
            period_from: ri.period_from,
            period_to: ri.period_to,
            customer_name: ri.customer_name,
            customer_representative: ri.customer_representative,
            customer_ico: ri.customer_ico,
            customer_dic: ri.customer_dic,
            customer_vat_number: ri.customer_vat_number,
            customer_street: ri.customer_street,
            customer_city: ri.customer_city,
            customer_postal_code: ri.customer_postal_code,
            customer_country: ri.customer_country,
            customer_email: ri.customer_email,
            note: ri.note,
            items: ri.items.map((item) => ({ ...item })),
            constant_symbol: ri.constant_symbol,
            header_text: ri.header_text,
            footer_text: ri.footer_text,
            deposit: toNumber(ri.deposit),
            payment_type: ri.payment_type,
            currency: ri.currency,
            rounding_mode: ri.rounding_mode,
        };
    }

    return {
        client_id: null,
        cleaning_object_id: null,
        name: '',
        type: 'monthly',
        template: '',
        frequency: 'monthly',
        day_of_month: 1,
        auto_issue: false,
        start_date: toDateInputValue(new Date()),
        end_date: null,
        occurrences_limit: null,
        due_days: 14,
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
        header_text: null,
        footer_text: null,
        deposit: 0,
        payment_type: props.context.defaults.payment_type,
        currency: props.context.defaults.currency,
        rounding_mode: props.context.defaults.rounding_mode,
    };
}

const form = useForm<RecurringInvoiceFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/recurring-invoices/${props.recurringInvoice!.id}` : '/recurring-invoices',
    initialData(),
);

form.transform((data: RecurringInvoiceFormData) => ({
    ...data,
    template: data.template || null,
    header_text: data.header_text || null,
    footer_text: data.footer_text || null,
    note: data.note || null,
}));

const ui = reactive({
    subjectMode: initialMode(props.recurringInvoice),
    endMode: initialEndMode(props.recurringInvoice),
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
const frequencyOptions = computed<SelectOption[]>(() => enumOptions(RECURRING_FREQUENCIES, recurringFrequencyKey, t));
const paymentTypeOptions = computed<SelectOption[]>(() => enumOptions(PAYMENT_TYPES, paymentTypeKey, t));
const currencyOptions = computed<SelectOption[]>(() => enumOptions(CURRENCIES, currencyKey, t));
const roundingModeOptions = computed<SelectOption[]>(() => enumOptions(ROUNDING_MODES, roundingModeKey, t));

const endModeOptions = computed<RadioOption[]>(() => [
    { value: 'forever', label: t('recurring_invoice_termination_forever') },
    { value: 'until_date', label: t('recurring_invoice_termination_until_date') },
    { value: 'count', label: t('recurring_invoice_termination_count') },
]);

const autoIssueHint = computed(() =>
    t('recurring_invoice_auto_issue_hint', {
        state: t(recurringDefaultStateKey(props.context.recurring_default_state)),
    }),
);

const previewDueDate = computed(() => toDateInputValue(addDays(parseISO(form.start_date), form.due_days)));

function onEndModeChange(mode: string): void {
    ui.endMode = mode as EndMode;
    if (mode === 'forever') {
        form.end_date = null;
        form.occurrences_limit = null;
    } else if (mode === 'until_date') {
        form.occurrences_limit = null;
    } else {
        form.end_date = null;
    }
}

function updateStartDate(value: string | null): void {
    form.start_date = value ?? '';
    callValidate(form, 'start_date');
}

function updateNullableDate(field: 'end_date' | 'period_from' | 'period_to', value: string | null): void {
    form[field] = value;
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
                        <TextInput field="name" :label="t('recurring_invoice_name')" required />
                    </div>
                </div>

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
                        <h2 class="card-title text-base">{{ t('recurring_invoice_section_schedule') }}</h2>

                        <RadioGroup field="type" :label="t('type')" :options="typeOptions" />

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <SelectInput
                                field="frequency"
                                :label="t('recurring_invoice_frequency')"
                                :options="frequencyOptions"
                            />

                            <NumberInput
                                :model-value="form.day_of_month"
                                :label="t('recurring_invoice_day_of_month')"
                                :min="1"
                                :max="28"
                                required
                                :error="form.errors.day_of_month"
                                @update:model-value="
                                    form.day_of_month = $event ?? 1;
                                    callValidate(form, 'day_of_month');
                                "
                            />

                            <SelectInput field="template" :label="t('invoice_template')" :options="templateOptions" />

                            <DateInput
                                :model-value="form.start_date"
                                :label="t('recurring_invoice_start_date')"
                                required
                                :error="form.errors.start_date"
                                @update:model-value="updateStartDate($event)"
                            />

                            <template v-if="form.type !== 'one_off'">
                                <DateInput
                                    :model-value="form.period_from"
                                    :label="t('invoice_period_from')"
                                    :error="form.errors.period_from"
                                    @update:model-value="updateNullableDate('period_from', $event)"
                                />

                                <DateInput
                                    :model-value="form.period_to"
                                    :label="t('invoice_period_to')"
                                    :error="form.errors.period_to"
                                    @update:model-value="updateNullableDate('period_to', $event)"
                                />
                            </template>

                            <NumberInput
                                :model-value="form.due_days"
                                :label="t('recurring_invoice_due_days')"
                                :min="0"
                                :error="form.errors.due_days"
                                @update:model-value="
                                    form.due_days = $event ?? 0;
                                    callValidate(form, 'due_days');
                                "
                            />
                        </div>

                        <RadioGroup
                            :model-value="ui.endMode"
                            :label="t('recurring_invoice_termination')"
                            :options="endModeOptions"
                            @update:model-value="onEndModeChange"
                        />

                        <DateInput
                            v-if="ui.endMode === 'until_date'"
                            :model-value="form.end_date"
                            :label="t('recurring_invoice_end_date')"
                            :error="form.errors.end_date"
                            @update:model-value="updateNullableDate('end_date', $event)"
                        />

                        <NumberInput
                            v-if="ui.endMode === 'count'"
                            :model-value="form.occurrences_limit"
                            :label="t('recurring_invoice_occurrences_limit')"
                            :min="1"
                            :error="form.errors.occurrences_limit"
                            @update:model-value="
                                form.occurrences_limit = $event;
                                callValidate(form, 'occurrences_limit');
                            "
                        />

                        <ToggleInput field="auto_issue" :label="t('recurring_invoice_auto_issue')" />
                        <p class="text-sm text-base-content/60">{{ autoIssueHint }}</p>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_payment') }}</h2>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <TextInput field="constant_symbol" :label="t('invoice_pdf_constant_symbol')" />
                            <SelectInput
                                field="payment_type"
                                :label="t('invoice_pdf_payment_type')"
                                :options="paymentTypeOptions"
                            />
                            <SelectInput field="currency" :label="t('invoice_currency')" :options="currencyOptions" />
                            <SelectInput
                                field="rounding_mode"
                                :label="t('invoice_rounding_mode')"
                                :options="roundingModeOptions"
                            />
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
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoice_section_items') }}</h2>
                        <InvoiceItemsEditor
                            field="items"
                            :is-vat-payer="context.is_vat_payer"
                            :vat-rate-options="context.vat_rate_options"
                            :currency="form.currency"
                            :blank-row="blankItem"
                        />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-4">
                        <h2 class="card-title text-base">{{ t('invoice_section_texts') }}</h2>

                        <TextareaInput
                            :model-value="form.header_text ?? ''"
                            :label="t('invoice_header_text')"
                            :error="form.errors.header_text"
                            @update:model-value="form.header_text = $event"
                        />

                        <TextareaInput
                            :model-value="form.footer_text ?? ''"
                            :label="t('invoice_footer_text')"
                            :error="form.errors.footer_text"
                            @update:model-value="form.footer_text = $event"
                        />

                        <TextareaInput
                            :model-value="form.note ?? ''"
                            :label="t('note')"
                            :error="form.errors.note"
                            @update:model-value="form.note = $event"
                        />
                    </div>
                </div>

                <FormActions
                    cancel-href="/recurring-invoices"
                    :submit-label="isEditing ? t('save') : t('recurring_invoice_add')"
                    :processing="form.processing"
                />
            </div>

            <div>
                <InvoiceFormSummary
                    class="lg:sticky lg:top-4"
                    :number="null"
                    :type="form.type"
                    :issue-date="form.start_date"
                    :due-date="previewDueDate"
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
