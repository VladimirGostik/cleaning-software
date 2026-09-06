<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const { t } = useI18n();

const isObjectParty = computed(() => props.contract.contractable_type === 'cleaning_object');

const hasLinks = computed(
    () => props.contract.quote_id !== null || isObjectParty.value || props.contract.contract_template_name !== null,
);
</script>

<template>
    <div v-if="hasLinks" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3 text-sm">
            <h2 class="card-title text-base">{{ t('contract_section_links') }}</h2>

            <p v-if="props.contract.quote_id">
                <span class="text-base-content/60">{{ t('contract_link_quote') }}:</span>
                <Link :href="`/quotes/${props.contract.quote_id}`" class="link link-hover ml-1">
                    {{ props.contract.quote_number ?? t('quote_no_number') }}
                </Link>
            </p>

            <p v-if="isObjectParty">
                <span class="text-base-content/60">{{ t('contract_link_object') }}:</span>
                <Link :href="`/objects/${props.contract.contractable_id}`" class="link link-hover ml-1">
                    {{ props.contract.contractable_label }}
                </Link>
            </p>

            <p v-if="props.contract.contract_template_name">
                <span class="text-base-content/60">{{ t('contract_link_template') }}:</span>
                <span class="ml-1">{{ props.contract.contract_template_name }}</span>
            </p>
        </div>
    </div>
</template>
