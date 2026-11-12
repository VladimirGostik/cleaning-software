<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ContractTermBadge from './ContractTermBadge.vue';
import { formatDate, formatDatetime } from '@/utils/date';
import { contractSignatureLabel } from '@/utils/contracts';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div v-if="props.contract.number">
            <p class="text-xs text-muted">{{ t('contract_number') }}</p>
            <p class="tabular-nums">{{ props.contract.number }}</p>
        </div>

        <div>
            <p class="text-xs text-muted">{{ t('contract_term_type') }}</p>
            <ContractTermBadge :term-type="props.contract.term_type" />
        </div>

        <div>
            <p class="text-xs text-muted">{{ t('contract_valid_from') }}</p>
            <p>{{ formatDate(props.contract.valid_from) }}</p>
        </div>

        <div>
            <p class="text-xs text-muted">{{ t('contract_end_date') }}</p>
            <p :class="{ 'text-error': props.contract.status === 'expired' }">
                {{ props.contract.end_date ? formatDate(props.contract.end_date) : t('contract_end_date_indefinite') }}
            </p>
        </div>

        <div>
            <p class="text-xs text-muted">{{ t('contract_signed_at') }}</p>
            <p>{{ contractSignatureLabel(t, props.contract) }}</p>
        </div>

        <div v-if="props.contract.terminated_at" class="col-span-2 sm:col-span-4">
            <p class="text-xs text-muted">{{ t('contract_terminated_at') }}</p>
            <p>{{ formatDatetime(props.contract.terminated_at) }}</p>
            <p v-if="props.contract.termination_reason" class="mt-1 whitespace-pre-wrap text-muted">
                {{ t('contract_termination_reason') }}: {{ props.contract.termination_reason }}
            </p>
        </div>
    </div>
</template>
