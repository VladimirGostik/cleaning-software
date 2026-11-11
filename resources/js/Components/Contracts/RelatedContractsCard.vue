<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractFileMarker from '@/Components/Contracts/ContractFileMarker.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import { formatDate } from '@/utils/date';

defineProps<{
    contracts: App.Data.Contracts.ContractListItemData[] | null;
    canCreate: boolean;
    createHref: string;
}>();

const { t } = useI18n();
</script>

<template>
    <div v-if="contracts !== null || canCreate" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="card-title text-base">{{ t('contracts') }}</h2>
                <Link v-if="canCreate" :href="createHref" class="btn btn-primary btn-sm">
                    {{ t('contract_add') }}
                </Link>
            </div>

            <p v-if="contracts !== null && contracts.length === 0" class="text-sm text-base-content/60">
                {{ t('contracts_empty') }}
            </p>

            <ul v-else-if="contracts !== null" class="divide-y divide-base-200">
                <li v-for="contract in contracts" :key="contract.id" class="flex flex-wrap items-center gap-2 py-2">
                    <div class="min-w-0 flex-1">
                        <Link :href="`/contracts/${contract.id}`" class="link link-hover font-medium">
                            {{ contract.title }}
                        </Link>
                        <p class="text-xs text-base-content/60">
                            <span v-if="contract.number" class="tabular-nums">{{ contract.number }} · </span>
                            {{ contract.contractable_label }} · {{ formatDate(contract.valid_from) }}
                        </p>
                    </div>
                    <ContractCategoryBadge :category="contract.category" />
                    <ContractStatusBadge :status="contract.status" />
                    <ContractFileMarker :source="contract.source" :is-signed="contract.is_signed" />
                </li>
            </ul>
        </div>
    </div>
</template>
