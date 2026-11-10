<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AddIcon from '~icons/material-symbols/add-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';

import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import {
    MAX_EXPENSE_LINES,
    emptyLine,
    linesTotal,
    type ExpenseLineKind,
    type ExpenseLineRow,
} from '@/utils/expenseLines';

const props = defineProps<{
    kind: ExpenseLineKind;
    lines: ExpenseLineRow[];
    employeeOptions: SelectOption[];
    errors: Record<string, string>;
    disabled: boolean;
}>();

const emit = defineEmits<{
    'update:lines': [ExpenseLineRow[]];
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const total = computed(() => money(linesTotal(props.lines), 'EUR'));
const canAdd = computed(() => !props.disabled && props.lines.length < MAX_EXPENSE_LINES);
const canRemove = computed(() => !props.disabled && props.lines.length > 1);
const options = computed<SelectOption[]>(() => [
    { value: '', label: t('expense_payslip_pick_employee') },
    ...props.employeeOptions,
]);

function update(index: number, patch: Partial<ExpenseLineRow>): void {
    emit(
        'update:lines',
        props.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
    );
}

function add(): void {
    emit('update:lines', [...props.lines, emptyLine()]);
}

function remove(index: number): void {
    emit(
        'update:lines',
        props.lines.filter((_, i) => i !== index),
    );
}

function rowError(index: number, field: string): string | null {
    return props.errors[`lines.${index}.${field}`] ?? null;
}
</script>

<template>
    <div class="space-y-3" data-autom="expense-lines">
        <p class="text-sm font-medium">
            {{ kind === 'employee' ? t('expense_payslip_lines_employee') : t('expense_payslip_lines_recipient') }}
        </p>

        <ul class="space-y-3">
            <li
                v-for="(line, index) in lines"
                :key="line.key"
                class="rounded-box border border-base-300 p-3"
                data-autom="expense-line"
            >
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-start">
                    <div class="min-w-0">
                        <SelectInput
                            v-if="kind === 'employee'"
                            :model-value="line.tenant_membership_id"
                            :label="t('expense_payslip_employee_n', { n: index + 1 })"
                            label-class="sr-only"
                            :options="options"
                            :error="rowError(index, 'tenant_membership_id') ?? rowError(index, 'supplier_name')"
                            :disabled="disabled"
                            @update:model-value="update(index, { tenant_membership_id: $event, printed_name: null })"
                        />
                        <TextInput
                            v-else
                            :model-value="line.supplier_name"
                            :label="t('expense_payslip_recipient_n', { n: index + 1 })"
                            label-class="sr-only"
                            :placeholder="t('expense_payslip_recipient_placeholder')"
                            :error="rowError(index, 'supplier_name') ?? rowError(index, 'tenant_membership_id')"
                            :disabled="disabled"
                            @update:model-value="update(index, { supplier_name: $event })"
                        />
                        <p
                            v-if="kind === 'employee' && line.printed_name && !line.tenant_membership_id"
                            class="mt-1 text-sm text-warning"
                        >
                            {{ t('expense_payslip_printed_name', { name: line.printed_name }) }}
                        </p>
                    </div>

                    <TextInput
                        :model-value="line.amount"
                        :label="t('expense_payslip_amount_n', { n: index + 1 })"
                        label-class="sr-only"
                        :placeholder="t('expense_amount')"
                        :error="rowError(index, 'amount')"
                        :disabled="disabled"
                        class="text-right tabular-nums"
                        @update:model-value="update(index, { amount: $event })"
                    />

                    <button
                        v-if="canRemove"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square justify-self-end sm:mt-1"
                        :aria-label="t('expense_payslip_remove_line', { n: index + 1 })"
                        @click="remove(index)"
                    >
                        <DeleteIcon class="size-5" />
                    </button>
                </div>
            </li>
        </ul>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <button type="button" class="btn btn-neutral btn-sm h-auto min-h-8 py-1" :disabled="!canAdd" @click="add">
                <AddIcon class="size-5" />
                {{ kind === 'employee' ? t('expense_payslip_add_employee') : t('expense_payslip_add_recipient') }}
            </button>
            <p class="text-sm" data-autom="expense-lines-total">
                {{ t('expense_payslip_total') }}: <span class="font-semibold tabular-nums">{{ total }}</span>
            </p>
        </div>
    </div>
</template>
