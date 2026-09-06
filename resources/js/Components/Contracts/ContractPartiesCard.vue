<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePageProps } from '@/Composables/usePageProps';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();
const pageProps = usePageProps();

const supplierName = computed(() => pageProps.value.tenant.active?.name ?? t('empty_dash'));

const partyLabel = computed(() =>
    props.contract.contractable_type === 'cleaning_object' ? t('contract_party_object') : t('contract_party_employee'),
);
</script>

<template>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ t('contract_party_supplier') }}</h3>
            <p class="font-medium">{{ supplierName }}</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-base-content/70">{{ partyLabel }}</h3>
            <a
                v-if="contract.contractable_type === 'cleaning_object'"
                :href="`/objects/${contract.contractable_id}`"
                class="link link-hover font-medium"
            >
                {{ contract.contractable_label }}
            </a>
            <p v-else class="font-medium">{{ contract.contractable_label }}</p>

            <p v-if="contract.contract_template_name" class="mt-2 text-sm text-base-content/60">
                {{ t('contract_link_template') }}: {{ contract.contract_template_name }}
            </p>
        </div>
    </div>
</template>
