<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { formatMoney } from '@/utils/money';

defineProps<{
    count: number;
    amount: string;
    submitting: boolean;
}>();

const emit = defineEmits<{
    submit: [];
}>();

const { t, locale } = useI18n();
</script>

<template>
    <div
        class="kg-sticky-footer flex flex-wrap items-center justify-between gap-3 rounded-card-nested bg-base-200 px-4 py-3"
    >
        <p class="text-sm font-medium tabular-nums" aria-live="polite">
            {{ t('invoice_payment_import_selection', { count, amount: formatMoney(amount, 'EUR', locale) }) }}
        </p>
        <button
            type="button"
            class="btn btn-secondary w-full sm:w-auto"
            :disabled="submitting || count === 0"
            @click="emit('submit')"
        >
            <span v-if="submitting" class="loading loading-spinner loading-xs" />
            {{ t('invoice_payment_import_submit') }}
        </button>
    </div>
</template>
