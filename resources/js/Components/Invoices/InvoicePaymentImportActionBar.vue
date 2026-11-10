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
        class="sticky bottom-0 z-10 -mx-6 -mb-6 flex flex-wrap items-center justify-between gap-3 border-t border-base-300 bg-base-100 pl-6 pr-24 py-3"
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
