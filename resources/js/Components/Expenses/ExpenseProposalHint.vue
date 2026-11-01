<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { expenseCategoryKey } from '@/utils/enums';

const props = defineProps<{
    proposal: App.Data.Expenses.ExpenseProposalData;
    companies: App.Data.Auth.MobileCompanyData[];
}>();

const { t } = useI18n();

const companyName = computed(() => props.companies.find((c) => c.tenant_id === props.proposal.tenant_id)?.name ?? null);
const percent = computed(() => Math.round(props.proposal.confidence * 100));
</script>

<template>
    <div class="alert alert-info alert-soft text-sm">
        <div>
            <p class="font-medium">{{ t('expense_ai_suggestion') }}</p>
            <p v-if="proposal.category">{{ t('expense_category') }}: {{ t(expenseCategoryKey(proposal.category)) }}</p>
            <p v-if="companyName">{{ t('expense_ai_company') }}: {{ companyName }}</p>
            <p>{{ t('expense_ai_confidence') }}: {{ percent }}%</p>
            <p v-if="proposal.reason">{{ t('expense_ai_reason') }}: {{ proposal.reason }}</p>
        </div>
    </div>
</template>
