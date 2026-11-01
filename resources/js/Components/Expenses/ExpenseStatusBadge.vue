<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { expenseStatusKey } from '@/utils/enums';

defineProps<{
    status: App.Enums.ExpenseStatusEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm" :class="status === 'confirmed' ? 'badge-success' : 'badge-warning'">
        {{ t(expenseStatusKey(status)) }}
    </span>
</template>
