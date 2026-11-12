<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { formatDate, formatMonth } from '@/utils/date';

const props = defineProps<{
    employeeId: string;
    expenses: App.Data.Expenses.ExpenseListItemData[];
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const payslips = computed(() => props.expenses.filter((e) => !e.is_undocumented && e.category === 'wages'));
const cash = computed(() => props.expenses.filter((e) => e.is_undocumented));

const payslipsHref = computed(() => `/expenses?filter[tenant_membership_id]=${props.employeeId}`);
const cashHref = computed(() => `${payslipsHref.value}&filter[undocumented]=1`);
</script>

<template>
    <div class="card bg-base-100" data-autom="employee-expenses-card">
        <div class="card-body space-y-4">
            <h2 class="card-title">{{ t('employee_expenses_title') }}</h2>

            <p v-if="payslips.length === 0 && cash.length === 0" class="text-sm text-muted">
                {{ t('employee_expenses_empty') }}
            </p>

            <section v-if="payslips.length > 0" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-medium">{{ t('employee_expenses_payslips') }}</h3>
                    <Link :href="payslipsHref" class="link link-hover text-sm">{{ t('employee_expenses_all') }}</Link>
                </div>
                <ul class="kg-list">
                    <li
                        v-for="expense in payslips"
                        :key="expense.id"
                        class="kg-list-row flex-wrap justify-between gap-x-4 gap-y-1"
                        data-autom="employee-payslip"
                    >
                        <Link :href="`/expenses/${expense.id}`" class="link link-hover font-medium">
                            {{ formatMonth(expense.payroll_month) }}
                        </Link>
                        <span class="tabular-nums">{{ money(expense.amount, expense.currency) }}</span>
                        <a
                            v-if="expense.has_receipt"
                            :href="`/expenses/${expense.id}/receipt`"
                            class="link text-sm"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ t('employee_expenses_pdf') }}
                        </a>
                    </li>
                </ul>
            </section>

            <section v-if="cash.length > 0" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-medium">{{ t('employee_expenses_cash') }}</h3>
                    <Link :href="cashHref" class="link link-hover text-sm">{{ t('employee_expenses_all') }}</Link>
                </div>
                <ul class="kg-list">
                    <li
                        v-for="expense in cash"
                        :key="expense.id"
                        class="kg-list-row flex-wrap justify-between gap-x-4 gap-y-1"
                        data-autom="employee-cash"
                    >
                        <Link :href="`/expenses/${expense.id}`" class="link link-hover font-medium">
                            {{ formatDate(expense.date) }}
                        </Link>
                        <span class="tabular-nums">{{ money(expense.amount, expense.currency) }}</span>
                        <span v-if="expense.description" class="basis-full text-sm text-muted">
                            {{ expense.description }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
