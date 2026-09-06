<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ContractTermBadge from './ContractTermBadge.vue';
import { formatDate, formatDatetime } from '@/utils/date';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div v-if="props.contract.number">
            <p class="text-xs text-base-content/50">{{ t('contract_number') }}</p>
            <p class="font-mono">{{ props.contract.number }}</p>
        </div>

        <div>
            <p class="text-xs text-base-content/50">{{ t('contract_term_type') }}</p>
            <ContractTermBadge :term-type="props.contract.term_type" />
        </div>

        <div>
            <p class="text-xs text-base-content/50">{{ t('contract_valid_from') }}</p>
            <p>{{ formatDate(props.contract.valid_from) }}</p>
        </div>

        <div>
            <p class="text-xs text-base-content/50">{{ t('contract_end_date') }}</p>
            <p :class="{ 'text-error': props.contract.status === 'expired' }">
                {{ props.contract.end_date ? formatDate(props.contract.end_date) : t('contract_end_date_indefinite') }}
            </p>
        </div>

        <div>
            <p class="text-xs text-base-content/50">{{ t('contract_signed_at') }}</p>
            <p>{{ props.contract.signed_at ? formatDatetime(props.contract.signed_at) : t('contract_not_signed') }}</p>
        </div>

        <div v-if="props.contract.terminated_at" class="col-span-2 sm:col-span-4">
            <p class="text-xs text-base-content/50">{{ t('contract_terminated_at') }}</p>
            <p>{{ formatDatetime(props.contract.terminated_at) }}</p>
            <p v-if="props.contract.termination_reason" class="mt-1 whitespace-pre-wrap text-base-content/70">
                {{ t('contract_termination_reason') }}: {{ props.contract.termination_reason }}
            </p>
        </div>
    </div>
</template>
