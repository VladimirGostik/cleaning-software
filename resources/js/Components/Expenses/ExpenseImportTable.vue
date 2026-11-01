<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { EXPENSE_CATEGORIES, expenseCategoryKey } from '@/utils/enums';

interface ImportRow {
    row: number;
    date: string;
    amount: string;
    counterparty: string | null;
    note: string | null;
    reference: string | null;
    proposed_category: App.Enums.ExpenseCategoryEnum | null;
    confidence: number;
    duplicate: boolean;
    include: boolean;
}

defineProps<{
    rows: ImportRow[];
}>();

const { t } = useI18n();

function onCategory(row: ImportRow, value: string): void {
    row.proposed_category = value === '' ? null : (value as App.Enums.ExpenseCategoryEnum);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>{{ t('expense_import_include') }}</th>
                    <th>{{ t('expense_date') }}</th>
                    <th>{{ t('expense_amount') }}</th>
                    <th>{{ t('expense_import_counterparty') }}</th>
                    <th>{{ t('expense_import_note') }}</th>
                    <th>{{ t('expense_category') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.row">
                    <td>
                        <input
                            v-model="row.include"
                            type="checkbox"
                            class="checkbox checkbox-sm"
                            :aria-label="t('expense_import_include')"
                        />
                    </td>
                    <td>{{ row.date }}</td>
                    <td>{{ row.amount }}</td>
                    <td>
                        {{ row.counterparty ?? t('empty_dash') }}
                        <span v-if="row.duplicate" class="badge badge-warning badge-sm ml-1">
                            {{ t('expense_import_duplicate') }}
                        </span>
                    </td>
                    <td>{{ row.note ?? t('empty_dash') }}</td>
                    <td>
                        <select
                            class="select select-sm"
                            :value="row.proposed_category ?? ''"
                            :aria-label="t('expense_category')"
                            @change="onCategory(row, ($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">{{ t('expense_category_none') }}</option>
                            <option v-for="c in EXPENSE_CATEGORIES" :key="c" :value="c">
                                {{ t(expenseCategoryKey(c)) }}
                            </option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
