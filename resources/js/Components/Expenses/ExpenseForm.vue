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
import ExpenseLinesField from './ExpenseLinesField.vue';
import ExpenseDuplicateModal from './ExpenseDuplicateModal.vue';

import { useToast } from '@/Composables/useToast';
import { usePageProps } from '@/Composables/usePageProps';
import { useExpenseDuplicateChoice } from '@/Composables/useExpenseDuplicateChoice';
import { monthOptions, previousMonth, toDateInputValue } from '@/utils/date';
import { CURRENCIES, EXPENSE_CATEGORIES, expenseCategoryKey } from '@/utils/enums';
import {
    batchPayload,
    emptyLine,
    employeeOptionsFor,
    lineKindFor,
    linesFromDraft,
    type ExpenseLineRow,
} from '@/utils/expenseLines';
import { describeRequestError, uploadTemporaryFile } from '@/utils/jsonRequest';

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
    tenant_membership_id: string;
    payroll_month: string;
    duplicate_action: App.Enums.ExpenseDuplicateActionEnum | null;
}

const props = defineProps<{
    context: App.Data.Expenses.ExpenseFormContextData;
    expense?: App.Data.Expenses.ExpenseDetailData | null;
}>();

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t, locale } = useI18n();
const pageProps = usePageProps();
const toast = useToast();

const isEdit = computed(() => !!props.expense);
const activeTenantId = computed(() => pageProps.value.tenant.active?.id ?? '');
const receiptFile = ref<File | null>(null);
const proposal = ref<App.Data.Expenses.ExpenseProposalData | null>(null);
const lines = ref<ExpenseLineRow[]>([emptyLine()]);
const recognizedLines = ref(0);
const staging = ref(false);
const stagedUuids = new WeakMap<File, string>();

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
        tenant_membership_id: props.expense?.tenant_membership_id ?? '',
        payroll_month: props.expense?.payroll_month ?? previousMonth(),
        duplicate_action: null,
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

const employeeOptions = computed<SelectOption[]>(() =>
    employeeOptionsFor(props.context.employees, form.tenant_id, t('expense_payslip_inactive')),
);

const lineKind = computed(() => lineKindFor(form.category, isEdit.value, employeeOptions.value.length > 0));
const showEditEmployee = computed(() => isEdit.value && form.category === 'wages' && employeeOptions.value.length > 0);
const showMonth = computed(
    () => lineKind.value !== null || (showEditEmployee.value && form.tenant_membership_id !== ''),
);
const monthSelectOptions = computed(() => monthOptions(locale.value, 18, form.payroll_month || null));
const editEmployeeOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('expense_payslip_no_employee') },
    ...employeeOptions.value,
]);

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

    recognizedLines.value = draft.lines.length;
    if (draft.payroll_month) form.payroll_month = draft.payroll_month;
    if (draft.lines.length > 0 && (form.category === 'wages' || form.category === 'payroll_levies')) {
        lines.value = linesFromDraft(draft.lines);
        form.payroll_month = draft.payroll_month ?? draft.lines[0]?.payroll_month ?? previousMonth();
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
        const own = new Set(props.context.employees.filter((e) => e.tenant_id === tenantId).map((e) => e.id));
        lines.value = lines.value.map((line) =>
            line.tenant_membership_id === '' || own.has(line.tenant_membership_id)
                ? line
                : { ...line, tenant_membership_id: '' },
        );
        if (form.tenant_membership_id !== '' && !own.has(form.tenant_membership_id)) form.tenant_membership_id = '';

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

    if (isEdit.value) {
        payload.tenant_membership_id = data.tenant_membership_id === '' ? null : data.tenant_membership_id;
        payload.payroll_month =
            data.tenant_membership_id === '' || data.payroll_month === '' ? null : data.payroll_month;
        if (data.duplicate_action) payload.duplicate_action = data.duplicate_action;
    }

    if (!isEdit.value) {
        payload.source = data.source;
        payload.ai_proposal = data.ai_proposal;
        if (data.tenant_id !== activeTenantId.value) payload.tenant_id = data.tenant_id;
    }

    return payload;
}

function handleErrors(errors: Record<string, string>): void {
    duplicate.offer(errors);
}

async function stageReceipt(file: File | null): Promise<string | null> {
    if (!file) return null;
    const cached = stagedUuids.get(file);
    if (cached) return cached;

    staging.value = true;
    try {
        const { uuid } = await uploadTemporaryFile(file);
        stagedUuids.set(file, uuid);

        return uuid;
    } finally {
        staging.value = false;
    }
}

async function submitBatch(kind: NonNullable<typeof lineKind.value>): Promise<void> {
    let receiptUuid: string | null;
    try {
        receiptUuid = await stageReceipt(canAttachReceipt.value ? receiptFile.value : null);
    } catch (e) {
        toast.error(describeRequestError(e, t));

        return;
    }

    form.transform((data: ExpenseFormData) =>
        batchPayload(
            {
                ...(data.tenant_id !== activeTenantId.value ? { tenant_id: data.tenant_id } : {}),
                date: data.date,
                category: data.category,
                description: data.description,
                payroll_month: data.payroll_month,
                ai_proposal: data.ai_proposal,
                duplicate_action: data.duplicate_action,
            },
            kind,
            lines.value,
            receiptUuid,
        ),
    ).submit('post', '/expenses/batch', {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
        onError: handleErrors,
        onFinish: () => {
            form.duplicate_action = null;
        },
    });
}

function submitSingle(): void {
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
        onError: handleErrors,
        onFinish: () => {
            form.duplicate_action = null;
        },
    });
}

function submit(): void {
    if (lineKind.value) {
        void submitBatch(lineKind.value);
    } else {
        submitSingle();
    }
}

const duplicate = useExpenseDuplicateChoice((action) => {
    form.duplicate_action = action;
    submit();
});

const busy = computed(() => form.processing || staging.value);
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 p-6">
                <ExpenseAnalyzePanel v-if="!isEdit" @analyzed="applyDraft" />

                <ExpenseProposalHint
                    v-if="proposal"
                    :proposal="proposal"
                    :companies="context.companies"
                    :line-count="recognizedLines"
                />

                <SelectInput
                    v-if="!isEdit && context.companies.length > 1"
                    field="tenant_id"
                    :label="t('expense_company')"
                    :options="companyOptions"
                    required
                />

                <SelectInput
                    field="category"
                    :label="t('expense_payslip_type')"
                    :options="categoryOptions"
                    :required="isConfirmed"
                />

                <SelectInput
                    v-if="showEditEmployee"
                    field="tenant_membership_id"
                    :label="t('expense_payslip_employee')"
                    :options="editEmployeeOptions"
                />

                <SelectInput
                    v-if="showMonth"
                    field="payroll_month"
                    :label="t('expense_payslip_month')"
                    :options="monthSelectOptions"
                    required
                />

                <DateInput v-model="form.date" :label="t('expense_date')" :error="form.errors.date" required />

                <template v-if="lineKind">
                    <ExpenseLinesField
                        v-model:lines="lines"
                        :kind="lineKind"
                        :employee-options="employeeOptions"
                        :errors="form.errors as Record<string, string>"
                        :disabled="busy"
                    />
                    <p v-if="form.errors.lines" class="text-sm text-error">{{ form.errors.lines }}</p>
                </template>

                <template v-else>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <TextInput field="amount" :label="t('expense_amount')" required />
                        </div>
                        <SelectInput field="currency" :label="t('expense_currency')" :options="currencyOptions" />
                    </div>

                    <TextInput field="vat_amount" :label="t('expense_vat_amount')" />
                    <TextInput field="supplier_name" :label="t('expense_supplier')" />
                </template>

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
                <FormActions :processing="busy" @cancel="emit('cancel')" />
            </div>
        </form>

        <ExpenseDuplicateModal
            :open="duplicate.open.value"
            :messages="duplicate.messages.value"
            :processing="busy"
            @keep-both="duplicate.choose('keep_both')"
            @replace="duplicate.choose('replace')"
            @cancel="duplicate.cancel()"
        />
    </FormProvider>
</template>
