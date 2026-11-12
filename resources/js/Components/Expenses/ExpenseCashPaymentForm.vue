<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';

import { usePageProps } from '@/Composables/usePageProps';
import { toDateInputValue } from '@/utils/date';
import { employeeOptionsFor } from '@/utils/expenseLines';

interface CashFormData {
    tenant_id: string;
    tenant_membership_id: string;
    date: string;
    amount: string;
    description: string;
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

const isEdit = computed(() => !!props.expense);
const activeTenantId = computed(() => pageProps.value.tenant.active?.id ?? '');

function defaultTenantId(): string {
    const companies = props.context.companies;
    if (companies.some((c) => c.tenant_id === activeTenantId.value)) return activeTenantId.value;

    return companies[0]?.tenant_id ?? '';
}

const form = useForm<CashFormData>(
    isEdit.value ? 'put' : 'post',
    isEdit.value ? `/expenses/${props.expense!.id}` : '/expenses',
    {
        tenant_id: props.expense?.tenant_id ?? defaultTenantId(),
        tenant_membership_id: props.expense?.tenant_membership_id ?? '',
        date: props.expense?.date ?? toDateInputValue(new Date()),
        amount: props.expense?.amount ?? '',
        description: props.expense?.description ?? '',
    },
);

const companyOptions = computed<SelectOption[]>(() =>
    props.context.companies.map((c) => ({ value: c.tenant_id, label: c.name })),
);

const employeeOptions = computed<SelectOption[]>(() =>
    employeeOptionsFor(props.context.employees, form.tenant_id, t('expense_payslip_inactive')),
);

const selectOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('expense_cash_pick_employee') },
    ...employeeOptions.value,
]);

function onCompanyChange(): void {
    if (!employeeOptions.value.some((o) => o.value === form.tenant_membership_id)) form.tenant_membership_id = '';
}

function buildPayload(data: CashFormData): Record<string, unknown> {
    const payload: Record<string, unknown> = {
        tenant_membership_id: data.tenant_membership_id === '' ? null : data.tenant_membership_id,
        date: data.date,
        amount: data.amount.replace(/\s+/g, '').replace(',', '.'),
        currency: 'EUR',
        category: 'wages',
        is_undocumented: true,
        payroll_month: null,
        description: data.description === '' ? null : data.description,
    };

    if (!isEdit.value) {
        payload.source = 'manual';
        if (data.tenant_id !== activeTenantId.value) payload.tenant_id = data.tenant_id;
    }

    return payload;
}

function submit(): void {
    form.transform((data: CashFormData) => buildPayload(data)).submit({
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="flex h-full flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 p-6">
                <p class="text-sm text-muted">{{ t('expense_cash_hint') }}</p>

                <SelectInput
                    v-if="!isEdit && context.companies.length > 1"
                    field="tenant_id"
                    :label="t('expense_company')"
                    :options="companyOptions"
                    required
                    @update:model-value="onCompanyChange"
                />

                <p v-if="employeeOptions.length === 0" class="alert alert-warning alert-soft text-sm">
                    {{ t('expense_cash_no_employees') }}
                </p>

                <SelectInput
                    field="tenant_membership_id"
                    :label="t('expense_cash_employee')"
                    :options="selectOptions"
                    required
                />

                <DateInput v-model="form.date" :label="t('expense_cash_date')" :error="form.errors.date" required />

                <TextInput field="amount" :label="t('expense_cash_amount')" required />

                <TextareaInput
                    v-model="form.description"
                    :label="t('expense_cash_note')"
                    :error="form.errors.description"
                    :rows="3"
                />
            </div>

            <div class="sticky bottom-0 bg-base-100 px-6 py-3">
                <FormActions
                    :processing="form.processing"
                    :disabled="employeeOptions.length === 0"
                    @cancel="emit('cancel')"
                />
            </div>
        </form>
    </FormProvider>
</template>
