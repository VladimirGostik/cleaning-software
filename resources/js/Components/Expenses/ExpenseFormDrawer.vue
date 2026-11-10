<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import ExpenseForm from './ExpenseForm.vue';
import ExpenseCashPaymentForm from './ExpenseCashPaymentForm.vue';

const props = defineProps<{
    open: boolean;
    context: App.Data.Expenses.ExpenseFormContextData;
    expense?: App.Data.Expenses.ExpenseDetailData | null;
    undocumented?: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const isCash = computed(() => props.expense?.is_undocumented ?? props.undocumented ?? false);
const title = computed(() => {
    if (isCash.value) return props.expense ? t('expense_cash_edit') : t('expense_cash_add');

    return props.expense ? t('expense_edit') : t('expense_add');
});
</script>

<template>
    <SideDrawer :open="open" :title="title" @close="emit('close')">
        <ExpenseCashPaymentForm
            v-if="isCash"
            :context="context"
            :expense="expense"
            @saved="emit('close')"
            @cancel="emit('close')"
        />
        <ExpenseForm v-else :context="context" :expense="expense" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
