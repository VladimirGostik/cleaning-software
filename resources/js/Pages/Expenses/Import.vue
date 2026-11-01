<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ExpenseImportAccountAlert from '@/Components/Expenses/ExpenseImportAccountAlert.vue';
import ExpenseImportResultCard from '@/Components/Expenses/ExpenseImportResultCard.vue';
import ExpenseImportSummary from '@/Components/Expenses/ExpenseImportSummary.vue';
import ExpenseImportTable from '@/Components/Expenses/ExpenseImportTable.vue';
import ExpenseImportUploadForm from '@/Components/Expenses/ExpenseImportUploadForm.vue';
import { useExpenseImport } from '@/Composables/useExpenseImport';
import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Expenses.ExpenseFormContextData;
    result: App.Data.Expenses.ExpenseImportResultData | null;
}>();

const { t } = useI18n();
const flow = useExpenseImport();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('expenses'), url: '/expenses' },
    { label: t('expense_import') },
];
</script>

<template>
    <Header :title="t('expense_import')" :breadcrumbs="breadcrumbs" />

    <ExpenseImportResultCard v-if="result" :result="result" />

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <ExpenseImportUploadForm
                :loading="flow.loading.value"
                :disabled="flow.submitting.value"
                :error="flow.error.value"
                @preview="flow.load"
                @change="flow.clear"
            />

            <template v-if="flow.preview.value">
                <ExpenseImportAccountAlert
                    v-if="flow.preview.value.account_check.status !== 'matched'"
                    :check="flow.preview.value.account_check"
                />

                <template v-else>
                    <ExpenseImportSummary
                        :iban="flow.preview.value.account_check.statement_iban"
                        :total="flow.totalMovements.value"
                        :counts="flow.counts.value"
                        :skipped-credit="flow.preview.value.skipped_credit_rows"
                        :skipped-other="flow.preview.value.skipped_other_rows"
                        :all-imported="flow.allAlreadyImported.value"
                    />

                    <EmptyState
                        v-if="flow.rows.value.length === 0"
                        :title="t('expense_import_no_expenses_title')"
                        :description="t('expense_import_no_expenses_description')"
                    />

                    <template v-else>
                        <ExpenseImportTable
                            :rows="flow.rows.value"
                            :disabled="flow.submitting.value"
                            @include="flow.setInclude"
                            @category="flow.setCategory"
                        />

                        <ul v-if="flow.submitErrors.value.length > 0" role="alert" class="text-sm text-error">
                            <li v-for="message in flow.submitErrors.value" :key="message">{{ message }}</li>
                        </ul>

                        <div class="flex justify-end">
                            <Link v-if="flow.allAlreadyImported.value" href="/expenses" class="btn btn-neutral">
                                {{ t('expense_import_back_to_expenses') }}
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="btn btn-secondary"
                                :disabled="flow.submitting.value || flow.selectedCount.value === 0"
                                @click="flow.submit"
                            >
                                <span v-if="flow.submitting.value" class="loading loading-spinner loading-xs" />
                                {{ t('expense_import_submit') }} ({{ flow.selectedCount.value }})
                            </button>
                        </div>
                    </template>
                </template>
            </template>
        </div>
    </div>
</template>
