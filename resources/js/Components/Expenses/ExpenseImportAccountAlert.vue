<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Can from '@/Components/Can.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';

const props = defineProps<{
    check: App.Data.Expenses.ExpenseImportAccountCheckData;
}>();

const { t } = useI18n();
const pageProps = usePageProps();
const { switchingTo, navigate } = useTenantSwitchVisit();

const activeName = computed(() => pageProps.value.tenant.active?.name ?? '');
const ownerId = computed(() => props.check.owner_tenant_id);
</script>

<template>
    <div role="alert" class="alert alert-warning items-start">
        <div v-if="check.status === 'other_company'" class="flex-1 space-y-1">
            <p class="font-semibold">{{ t('expense_import_other_company_title') }}</p>
            <p class="text-sm">
                {{
                    t('expense_import_other_company_body', {
                        iban: check.statement_iban,
                        company: check.owner_tenant_name,
                    })
                }}
            </p>
            <p class="text-sm">{{ t('expense_import_switch_hint') }}</p>
        </div>
        <div v-else-if="check.status === 'company_iban_missing'" class="flex-1 space-y-1">
            <p class="font-semibold">{{ t('expense_import_iban_missing_title') }}</p>
            <p class="text-sm">{{ t('expense_import_iban_missing_body', { company: activeName }) }}</p>
        </div>
        <div v-else class="flex-1 space-y-1">
            <p class="font-semibold">{{ t('expense_import_unknown_account_title') }}</p>
            <p class="text-sm">
                {{ t('expense_import_unknown_account_body', { iban: check.statement_iban, company: activeName }) }}
            </p>
        </div>

        <button
            v-if="check.status === 'other_company' && ownerId"
            type="button"
            class="btn btn-neutral btn-sm"
            :disabled="switchingTo !== null"
            @click="navigate(ownerId, '/expenses/import')"
        >
            <span v-if="switchingTo === ownerId" class="loading loading-spinner loading-xs" />
            {{ t('expense_import_switch_to_company', { company: check.owner_tenant_name }) }}
        </button>

        <Can v-if="check.status === 'company_iban_missing'" permission="manage billing settings">
            <Link href="/settings/invoicing" class="btn btn-neutral btn-sm">
                {{ t('expense_import_open_settings') }}
            </Link>
            <template #fallback>
                <p class="text-sm">{{ t('expense_import_iban_missing_no_permission') }}</p>
            </template>
        </Can>
    </div>
</template>
