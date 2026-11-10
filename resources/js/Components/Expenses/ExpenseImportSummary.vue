<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Can from '@/Components/Can.vue';

const STATES: readonly App.Enums.ExpenseImportRowStateEnum[] = [
    'new',
    'already_imported',
    'possible_match',
    'previously_deleted',
];

defineProps<{
    iban: string;
    total: number;
    counts: Record<App.Enums.ExpenseImportRowStateEnum, number>;
    skippedCredit: number;
    skippedOther: number;
    allImported: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-1 text-sm">
        <p>
            <span class="font-medium">{{ t('expense_import_account') }}:</span>
            <span class="tabular-nums">{{ ' ' }}{{ iban }}</span>
        </p>
        <p>
            <span class="font-medium">{{ t('expense_import_total_movements') }}:</span>
            {{ ' ' }}{{ total }}
        </p>
        <ul class="flex flex-wrap gap-x-4 gap-y-1">
            <li v-for="state in STATES" :key="state">{{ t(`expense_import_state_${state}`) }}: {{ counts[state] }}</li>
            <li v-if="skippedCredit > 0">
                {{ t('expense_import_skipped_credit') }}: {{ skippedCredit }}
                <Can permission="edit invoices">
                    <Link href="/invoices/payment-import" class="link ml-1">{{
                        t('invoice_payment_import_from_expenses')
                    }}</Link>
                </Can>
            </li>
            <li v-if="skippedOther > 0">{{ t('expense_import_skipped_other') }}: {{ skippedOther }}</li>
        </ul>
        <p v-if="allImported" class="font-medium">{{ t('expense_import_all_imported') }}</p>
        <p v-else-if="counts.already_imported > 0" class="text-muted">{{ t('expense_import_no_duplicates_line') }}</p>
        <p v-if="counts.possible_match > 0" class="text-muted">{{ t('expense_import_possible_match_hint') }}</p>
        <p v-if="counts.previously_deleted > 0" class="text-muted">{{ t('expense_import_previously_deleted_hint') }}</p>
    </div>
</template>
