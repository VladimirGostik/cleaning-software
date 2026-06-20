<script setup lang="ts">
    import { computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import Pagination from '@/Components/Pagination.vue';
    import ContractStatusBadge from '@/Components/Contracts/ContractStatusBadge.vue';
    import ContractFiltersBar from '@/Components/Contracts/ContractFiltersBar.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useAuthorization } from '@/Composables/useAuthorization';
    import { useContractFilters } from '@/Composables/useContractFilters';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import type { PaginatedData } from '@/types/pagination.d.ts';

    interface Props {
        contracts: PaginatedData<App.Data.Contracts.ContractListItemData>;
        filters: App.Data.Contracts.ContractIndexFilterData;
        statusOptions: SelectOption[];
        categoryOptions: SelectOption[];
        termTypeOptions: SelectOption[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const { can, hasFeature } = useAuthorization();
    const { formatDate } = useLocalizedDate();

    const { state: filterState } = useContractFilters(props.filters);

    const meta = computed(() => props.contracts.meta);
    const links = computed(() => props.contracts.links);

    function goToDetail(id: string): void {
        router.get(`/contracts/${id}`);
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('contracts.title')">
            <template #actions>
                <Can permission="create contracts" feature="contracts">
                    <a href="/contracts/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('contracts.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <ContractFiltersBar
            v-model:search="filterState.search"
            v-model:status="filterState.status"
            v-model:category="filterState.category"
            v-model:term-type="filterState.term_type"
            :status-options="statusOptions"
            :category-options="categoryOptions"
            :term-type-options="termTypeOptions"
        />

        <!-- Desktop table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('contracts.col.title') }}</th>
                            <th>{{ t('contracts.col.contractable') }}</th>
                            <th>{{ t('contracts.col.category') }}</th>
                            <th>{{ t('contracts.col.term_type') }}</th>
                            <th>{{ t('contracts.col.valid_from') }}</th>
                            <th>{{ t('contracts.col.end_date') }}</th>
                            <th>{{ t('contracts.col.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in contracts.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="goToDetail(row.id)"
                            @keydown.enter="goToDetail(row.id)"
                        >
                            <td class="font-medium">{{ row.title }}</td>
                            <td class="text-base-content/70 text-sm">
                                {{ row.contractable_display_name }}
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    {{ t('contract_category.' + row.category) }}
                                </span>
                            </td>
                            <td class="text-sm">
                                {{ t('contract_term_type.' + row.term_type) }}
                            </td>
                            <td class="text-sm text-base-content/70">
                                {{ formatDate(row.valid_from) }}
                            </td>
                            <td class="text-sm text-base-content/70">
                                {{ row.end_date ? formatDate(row.end_date) : t('common.empty_dash') }}
                            </td>
                            <td>
                                <ContractStatusBadge :status="row.status" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden flex flex-col gap-2">
            <div
                v-for="row in contracts.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm p-4 cursor-pointer"
                role="button"
                tabindex="0"
                @click="goToDetail(row.id)"
                @keydown.enter="goToDetail(row.id)"
            >
                <div class="flex justify-between items-start gap-2">
                    <span class="font-medium">{{ row.title }}</span>
                    <ContractStatusBadge :status="row.status" />
                </div>
                <div class="text-sm text-base-content/60 mt-1">
                    {{ row.contractable_display_name }}
                </div>
                <div class="text-xs text-base-content/50 mt-1">
                    {{ formatDate(row.valid_from) }}
                </div>
            </div>
        </div>

        <EmptyState
            v-if="contracts.data.length === 0"
            :title="t('contracts.empty')"
            :description="t('contracts.empty_hint')"
            :icon="ClipboardDocumentListIcon"
        >
            <template v-if="can('create contracts') && hasFeature('contracts')" #cta>
                <a href="/contracts/create" class="btn btn-primary btn-sm">
                    {{ t('contracts.add') }}
                </a>
            </template>
        </EmptyState>

        <Pagination v-if="meta.last_page > 1" :meta="meta" :links="links" />
    </div>
</template>
