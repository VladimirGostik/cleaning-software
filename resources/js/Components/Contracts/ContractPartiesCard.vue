<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePageProps } from '@/Composables/usePageProps';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();
const pageProps = usePageProps();

const supplierName = computed(() => pageProps.value.tenant.active?.name ?? t('empty_dash'));

const PARTY_LABEL_KEYS: Record<App.Enums.ContractableTypeEnum, string> = {
    cleaning_object: 'contract_party_object',
    tenant_membership: 'contract_party_employee',
    client: 'contract_party_client',
};

const partyLabel = computed(() => t(PARTY_LABEL_KEYS[props.contract.contractable_type]));

const partyHref = computed(() => {
    switch (props.contract.contractable_type) {
        case 'cleaning_object':
            return `/objects/${props.contract.contractable_id}`;
        case 'client':
            return `/clients/${props.contract.contractable_id}`;
        default:
            return null;
    }
});
</script>

<template>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('contract_party_supplier') }}</h3>
            <p class="font-medium">{{ supplierName }}</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ partyLabel }}</h3>
            <Link v-if="partyHref" :href="partyHref" class="link link-hover font-medium">
                {{ contract.contractable_label }}
            </Link>
            <p v-else class="font-medium">{{ contract.contractable_label }}</p>

            <p v-if="contract.document_template_name" class="mt-2 text-sm text-base-content/60">
                {{ t('contract_link_template') }}: {{ contract.document_template_name }}
            </p>
        </div>
    </div>
</template>
