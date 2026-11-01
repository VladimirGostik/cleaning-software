<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { expenseCategoryKey } from '@/utils/enums';

defineProps<{
    category: App.Enums.ExpenseCategoryEnum;
}>();

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline">{{ t(expenseCategoryKey(category)) }}</span>
</template>
