<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Can from '@/Components/Can.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';

const props = defineProps<{
    check: App.Data.Expenses.ExpenseImportAccountCheckData;
    returnUrl: string;
}>();

const { t } = useI18n();
const pageProps = usePageProps();
const { switchingTo, navigate } = useTenantSwitchVisit();

const activeName = computed(() => pageProps.value.tenant.active?.name ?? '');
const ownerId = computed(() => props.check.owner_tenant_id);
</script>

<template>
    <div role="alert" class="alert alert-warning flex flex-col items-start gap-3">
        <div v-if="check.status === 'other_company'" class="flex-1 space-y-1">
            <p class="font-medium">{{ t('bank_statement_other_company_title') }}</p>
            <p class="text-sm">
                {{
                    t('bank_statement_other_company_body', {
                        iban: check.statement_iban,
                        company: check.owner_tenant_name,
                    })
                }}
            </p>
            <p class="text-sm">{{ t('bank_statement_switch_hint') }}</p>
        </div>
        <div v-else-if="check.status === 'company_iban_missing'" class="flex-1 space-y-1">
            <p class="font-medium">{{ t('bank_statement_iban_missing_title') }}</p>
            <p class="text-sm">{{ t('bank_statement_iban_missing_body', { company: activeName }) }}</p>
        </div>
        <div v-else class="flex-1 space-y-1">
            <p class="font-medium">{{ t('bank_statement_unknown_account_title') }}</p>
            <p class="text-sm">
                {{ t('bank_statement_unknown_account_body', { iban: check.statement_iban, company: activeName }) }}
            </p>
        </div>

        <button
            v-if="check.status === 'other_company' && ownerId"
            type="button"
            class="btn btn-neutral btn-sm h-auto min-h-8 py-1 text-left whitespace-normal"
            :disabled="switchingTo !== null"
            @click="navigate(ownerId, returnUrl)"
        >
            <span v-if="switchingTo === ownerId" class="loading loading-spinner loading-xs" />
            {{ t('bank_statement_switch_to_company', { company: check.owner_tenant_name }) }}
        </button>

        <Can v-if="check.status === 'company_iban_missing'" permission="manage billing settings">
            <Link
                href="/settings/invoicing"
                class="btn btn-neutral btn-sm h-auto min-h-8 py-1 text-left whitespace-normal"
            >
                {{ t('bank_statement_open_settings') }}
            </Link>
            <template #fallback>
                <p class="text-sm">{{ t('bank_statement_iban_missing_no_permission') }}</p>
            </template>
        </Can>
    </div>
</template>
