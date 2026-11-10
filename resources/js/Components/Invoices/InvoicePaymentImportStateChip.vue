<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import type { StatusTone } from '@/Components/statusTone';

defineProps<{
    state: App.Enums.PaymentImportRowStateEnum;
    manual: boolean;
}>();

const { t } = useI18n();

const TONE: Record<App.Enums.PaymentImportRowStateEnum, StatusTone> = {
    matched: 'done',
    partial: 'in_progress',
    overpaid: 'warning',
    possible_duplicate: 'warning',
    invoice_paid: 'warning',
    vs_not_found: 'danger',
    no_vs: 'danger',
    invoice_not_payable: 'neutral',
    previously_deleted: 'neutral',
    already_imported: 'neutral',
};
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-1">
        <StatusChip :tone="TONE[state]" :label="t(`payment_import_row_state_${state}`)" size="sm" />
        <span v-if="manual" class="text-xs text-muted">{{ t('invoice_payment_import_manual') }}</span>
    </span>
</template>
