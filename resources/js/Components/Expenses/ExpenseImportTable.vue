<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import ExpenseImportRowStateChip from '@/Components/Expenses/ExpenseImportRowStateChip.vue';
import { formatDate } from '@/utils/date';
import { EXPENSE_CATEGORIES, expenseCategoryKey } from '@/utils/enums';
import { formatMoney } from '@/utils/money';
import type { ImportRow } from '@/Composables/useExpenseImport';

defineProps<{
    rows: ImportRow[];
    disabled: boolean;
}>();

const emit = defineEmits<{
    include: [reference: string, value: boolean];
    category: [reference: string, value: App.Enums.ExpenseCategoryEnum | null];
}>();

const { t, locale } = useI18n();

const categoryOptions = [
    { value: '', label: t('expense_category_none') },
    ...EXPENSE_CATEGORIES.map((c) => ({ value: c, label: t(expenseCategoryKey(c)) })),
];

function hasLink(row: ImportRow): boolean {
    return row.existing_expense_id !== null && (row.state === 'already_imported' || row.state === 'possible_match');
}

function rowLabel(row: ImportRow, key: string): string {
    return `${t(key)} ${formatDate(row.date)} ${formatMoney(row.amount, 'EUR', locale.value)}`;
}

function onCategory(row: ImportRow, value: string): void {
    emit('category', row.reference, value === '' ? null : (value as App.Enums.ExpenseCategoryEnum));
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ t('expense_import_include') }}</th>
                    <th>{{ t('expense_import_state') }}</th>
                    <th>{{ t('expense_date') }}</th>
                    <th class="text-right">{{ t('expense_amount') }}</th>
                    <th>{{ t('expense_import_counterparty') }}</th>
                    <th>{{ t('expense_import_note') }}</th>
                    <th>{{ t('expense_category') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.reference"
                    :class="{ 'text-muted': row.state === 'already_imported' }"
                >
                    <td>
                        <CheckboxInput
                            :model-value="row.include"
                            :label="rowLabel(row, 'expense_import_include')"
                            label-class="sr-only"
                            :disabled="disabled || row.state === 'already_imported'"
                            @update:model-value="emit('include', row.reference, $event)"
                        />
                    </td>
                    <td>
                        <div class="flex flex-wrap items-center gap-2">
                            <ExpenseImportRowStateChip :state="row.state" />
                            <a
                                v-if="hasLink(row)"
                                :href="`/expenses/${row.existing_expense_id}`"
                                target="_blank"
                                rel="noopener"
                                class="link text-sm"
                            >
                                {{ t('expense_import_view_existing') }}
                            </a>
                        </div>
                    </td>
                    <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                    <td class="whitespace-nowrap text-right tabular-nums">
                        {{ formatMoney(row.amount, 'EUR', locale) }}
                    </td>
                    <td>{{ row.counterparty ?? t('empty_dash') }}</td>
                    <td class="max-w-xs">
                        <span class="line-clamp-2" :title="row.note ?? undefined">{{
                            row.note ?? t('empty_dash')
                        }}</span>
                    </td>
                    <td>
                        <SelectInput
                            :model-value="row.proposed_category ?? ''"
                            :label="rowLabel(row, 'expense_category')"
                            label-class="sr-only"
                            class="min-w-52"
                            :options="categoryOptions"
                            :disabled="disabled || row.state === 'already_imported'"
                            @update:model-value="onCategory(row, $event)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
