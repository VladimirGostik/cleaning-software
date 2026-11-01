<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SideDrawer from '@/Components/SideDrawer.vue';
import ExpenseForm from './ExpenseForm.vue';

const props = defineProps<{
    open: boolean;
    context: App.Data.Expenses.ExpenseFormContextData;
    expense?: App.Data.Expenses.ExpenseDetailData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const title = computed(() => (props.expense ? t('expense_edit') : t('expense_add')));
</script>

<template>
    <SideDrawer :open="open" :title="title" @close="emit('close')">
        <ExpenseForm :context="context" :expense="expense" @saved="emit('close')" @cancel="emit('close')" />
    </SideDrawer>
</template>
