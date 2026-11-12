<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ContractCategoryBadge from '@/Components/Contracts/ContractCategoryBadge.vue';
import ContractFileMarker from '@/Components/Contracts/ContractFileMarker.vue';
import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
import ListRow from '@/Components/ListRow.vue';
import { formatDate } from '@/utils/date';

defineProps<{
    contracts: App.Data.Contracts.ContractListItemData[] | null;
    canCreate: boolean;
    createHref: string;
}>();

const { t } = useI18n();
</script>

<template>
    <div v-if="contracts !== null || canCreate" class="card bg-base-100">
        <div class="card-body space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="card-title">{{ t('contracts') }}</h2>
                <Link v-if="canCreate" :href="createHref" class="btn btn-primary btn-sm">
                    {{ t('contract_add') }}
                </Link>
            </div>

            <p v-if="contracts !== null && contracts.length === 0" class="text-sm text-muted">
                {{ t('contracts_empty') }}
            </p>

            <ul v-else-if="contracts !== null" class="kg-list">
                <li v-for="contract in contracts" :key="contract.id">
                    <ListRow :title="contract.title" :href="`/contracts/${contract.id}`">
                        <template #description>
                            <span v-if="contract.number" class="tabular-nums">{{ contract.number }} · </span>
                            {{ contract.contractable_label }} · {{ formatDate(contract.valid_from) }}
                        </template>
                        <template #trailing>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <ContractCategoryBadge :category="contract.category" />
                                <ContractStatusBadge :status="contract.status" />
                                <ContractFileMarker :source="contract.source" :is-signed="contract.is_signed" />
                            </div>
                        </template>
                    </ListRow>
                </li>
            </ul>
        </div>
    </div>
</template>
