<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import FileInput from '@/Components/Forms/FileInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import ExpenseAnalyzePanel from './ExpenseAnalyzePanel.vue';
import ExpenseProposalHint from './ExpenseProposalHint.vue';

import { useToast } from '@/Composables/useToast';
import { usePageProps } from '@/Composables/usePageProps';
import { toDateInputValue } from '@/utils/date';
import { CURRENCIES, EXPENSE_CATEGORIES, expenseCategoryKey } from '@/utils/enums';

interface ExpenseFormData {
    date: string;
    amount: string;
    currency: App.Enums.CurrencyEnum;
    vat_amount: string;
    supplier_name: string;
    description: string;
    category: string;
    tenant_id: string;
    source: App.Enums.ExpenseSourceEnum;
    ai_proposal: App.Data.Expenses.ExpenseProposalData | null;
}

const props = defineProps<{
    context: App.Data.Expenses.ExpenseFormContextData;
    expense?: App.Data.Expenses.ExpenseDetailData | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();
const pageProps = usePageProps();
const toast = useToast();

const isEdit = computed(() => !!props.expense);
const activeTenantId = computed(() => pageProps.value.tenant.active?.id ?? '');
const receiptFile = ref<File | null>(null);
const proposal = ref<App.Data.Expenses.ExpenseProposalData | null>(null);

function defaultTenantId(): string {
    const companies = props.context.companies;
    if (companies.some((c) => c.tenant_id === activeTenantId.value)) return activeTenantId.value;

    return companies[0]?.tenant_id ?? '';
}

const form = useForm<ExpenseFormData>(
    isEdit.value ? 'put' : 'post',
    isEdit.value ? `/expenses/${props.expense!.id}` : '/expenses',
    {
        date: props.expense?.date ?? toDateInputValue(new Date()),
        amount: props.expense?.amount ?? '',
        currency: props.expense?.currency ?? 'EUR',
        vat_amount: props.expense?.vat_amount ?? '',
        supplier_name: props.expense?.supplier_name ?? '',
        description: props.expense?.description ?? '',
        category: props.expense?.category ?? '',
        tenant_id: props.expense?.tenant_id ?? defaultTenantId(),
        source: 'manual',
        ai_proposal: null,
    },
);

const isConfirmed = computed(() => props.expense?.status === 'confirmed');

const categoryOptions = computed<SelectOption[]>(() => [
    ...(isConfirmed.value ? [] : [{ value: '', label: t('expense_category_none') }]),
    ...EXPENSE_CATEGORIES.map((v) => ({ value: v, label: t(expenseCategoryKey(v)) })),
]);

const currencyOptions = computed<SelectOption[]>(() => CURRENCIES.map((v) => ({ value: v, label: v })));

const companyOptions = computed<SelectOption[]>(() =>
    props.context.companies.map((c) => ({ value: c.tenant_id, label: c.name })),
);

const canAttachReceipt = computed(() => !isEdit.value && form.tenant_id === activeTenantId.value);

function applyDraft(draft: App.Data.Expenses.ExpenseDraftData, file: File | null): void {
    if (draft.date) form.date = draft.date;
    if (draft.amount) form.amount = draft.amount;
    form.currency = draft.currency;
    form.vat_amount = draft.vat_amount ?? '';
    form.supplier_name = draft.supplier_name ?? '';
    form.description = draft.description ?? '';
    form.category = draft.proposal.category ?? '';

    const proposedTenant = draft.proposal.tenant_id;
    if (proposedTenant && props.context.companies.some((c) => c.tenant_id === proposedTenant)) {
        form.tenant_id = proposedTenant;
    }

    form.source = file ? 'receipt' : 'manual';
    form.ai_proposal = draft.proposal;
    proposal.value = draft.proposal;
    receiptFile.value = file;
}

function normalizeDecimal(value: string): string {
    return value.replace(/\s+/g, '').replace(',', '.');
}

watch(
    () => form.tenant_id,
    (tenantId) => {
        if (isEdit.value || tenantId === activeTenantId.value) return;
        receiptFile.value = null;
        form.source = 'manual';
    },
);

function buildPayload(data: ExpenseFormData): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        date: data.date,
        amount: normalizeDecimal(data.amount),
        currency: data.currency,
        vat_amount: normalizeDecimal(data.vat_amount) === '' ? null : normalizeDecimal(data.vat_amount),
        supplier_name: data.supplier_name === '' ? null : data.supplier_name,
        description: data.description === '' ? null : data.description,
        category: data.category === '' ? null : data.category,
    };

    if (!isEdit.value) {
        payload.source = data.source;
        payload.ai_proposal = data.ai_proposal;
        if (data.tenant_id !== activeTenantId.value) payload.tenant_id = data.tenant_id;
    }

    return payload;
}

function submit(): void {
    const file = canAttachReceipt.value ? receiptFile.value : null;

    form.transform((data: ExpenseFormData) => buildPayload(data)).submit({
        preserveScroll: true,
        onSuccess: (page: { component: string; props: Record<string, unknown> }) => {
            const created = (page.props as { expense?: { id?: string } }).expense;
            if (file && page.component === 'Expenses/Show' && created?.id) {
                router.post(
                    `/expenses/${created.id}/receipt`,
                    { receipt: file },
                    {
                        forceFormData: true,
                        onError: (errors: Record<string, string>) =>
                            toast.error(errors.receipt ?? Object.values(errors)[0] ?? t('expense_request_failed')),
                    },
                );
            }
            emit('saved');
        },
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 p-6">
                <ExpenseAnalyzePanel v-if="!isEdit" @analyzed="applyDraft" />

                <ExpenseProposalHint v-if="proposal" :proposal="proposal" :companies="context.companies" />

                <SelectInput
                    v-if="!isEdit && context.companies.length > 1"
                    field="tenant_id"
                    :label="t('expense_company')"
                    :options="companyOptions"
                    required
                />

                <DateInput v-model="form.date" :label="t('expense_date')" :error="form.errors.date" required />

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <TextInput field="amount" :label="t('expense_amount')" required />
                    </div>
                    <SelectInput field="currency" :label="t('expense_currency')" :options="currencyOptions" />
                </div>

                <TextInput field="vat_amount" :label="t('expense_vat_amount')" />
                <TextInput field="supplier_name" :label="t('expense_supplier')" />

                <SelectInput
                    field="category"
                    :label="t('expense_category')"
                    :options="categoryOptions"
                    :required="isConfirmed"
                />

                <TextareaInput
                    v-model="form.description"
                    :label="t('description')"
                    :error="form.errors.description"
                    :rows="3"
                />

                <FileInput
                    v-if="canAttachReceipt && !receiptFile"
                    :label="t('expense_receipt')"
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    @update:model-value="receiptFile = $event"
                />
                <p v-else-if="canAttachReceipt && receiptFile" class="text-sm">
                    {{ t('expense_receipt') }}: {{ receiptFile.name }}
                </p>

                <p v-if="!isEdit && !canAttachReceipt" class="text-sm text-base-content/60">
                    {{ t('expense_receipt_attach_later') }}
                </p>
            </div>

            <div class="sticky bottom-0 border-t border-base-300 bg-base-100 px-6 py-3">
                <FormActions :processing="form.processing" @cancel="emit('cancel')" />
            </div>
        </form>
    </FormProvider>
</template>
