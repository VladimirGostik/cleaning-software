<script setup lang="ts">
    import { computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, ArrowPathIcon, CheckIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import Pagination from '@/Components/Pagination.vue';
    import RecurringStatusBadge from '@/Components/RecurringInvoices/RecurringStatusBadge.vue';
    import RecurringFrequencyBadge from '@/Components/RecurringInvoices/RecurringFrequencyBadge.vue';
    import RecurringInvoiceFiltersBar from '@/Components/RecurringInvoices/RecurringInvoiceFiltersBar.vue';
    import InvoicesTabBar from '@/Components/Invoices/InvoicesTabBar.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useRecurringInvoiceFilters } from '@/Composables/useRecurringInvoiceFilters';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        recurringInvoices: PaginatedData<App.Data.RecurringInvoices.RecurringInvoiceListItemData>;
        filters: App.Data.RecurringInvoices.RecurringInvoiceIndexFilterData;
        statusOptions: Array<{ value: string; label: string }>;
        frequencyOptions: Array<{ value: string; label: string }>;
        clients: App.Data.Clients.ClientOptionData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useRecurringInvoiceFilters(props.filters);

    const meta = computed(() => props.recurringInvoices.meta);
    const links = computed(() => props.recurringInvoices.links);

    const subtitle = computed(() =>
        t('recurring_invoices.subtitle').replace('{count}', String(props.recurringInvoices.meta.total)),
    );

    function goToDetail(id: string): void {
        router.get(`/recurring-invoices/${id}`);
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('recurring_invoices.title')" :subtitle="subtitle">
            <template #actions>
                <Can permission="create recurring_invoices">
                    <a href="/recurring-invoices/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('recurring_invoices.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <InvoicesTabBar active="recurring" />

        <RecurringInvoiceFiltersBar
            v-model:search="filterState.search"
            v-model:status="filterState.status"
            v-model:frequency="filterState.frequency"
            v-model:client-id="filterState.client_id"
            :statuses="statusOptions"
            :frequencies="frequencyOptions"
            :clients="clients"
        />

        <!-- Desktop table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('recurring_invoices.col.name') }}</th>
                            <th>{{ t('recurring_invoices.col.frequency') }}</th>
                            <th>{{ t('recurring_invoices.col.status') }}</th>
                            <th>{{ t('recurring_invoices.col.next_run') }}</th>
                            <th>{{ t('recurring_invoices.col.occurrences') }}</th>
                            <th>{{ t('recurring_invoices.col.auto_issue') }}</th>
                            <th class="w-12" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in recurringInvoices.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="goToDetail(row.id)"
                            @keydown.enter="goToDetail(row.id)"
                        >
                            <td>
                                <p class="font-medium">{{ row.name }}</p>
                                <p v-if="row.customer_display_name" class="text-xs text-base-content/50">
                                    {{ row.customer_display_name }}
                                </p>
                                <p v-else class="text-xs text-base-content/40 italic">
                                    {{ t('recurring_invoices.no_customer') }}
                                </p>
                            </td>
                            <td>
                                <RecurringFrequencyBadge :frequency="row.frequency" />
                            </td>
                            <td>
                                <RecurringStatusBadge :status="row.status" />
                            </td>
                            <td class="text-sm font-mono">
                                {{ row.next_run_at ?? t('common.empty_dash') }}
                            </td>
                            <td class="text-sm font-mono">
                                {{ row.occurrences_generated }}/{{
                                    row.occurrences_limit !== null ? row.occurrences_limit : '∞'
                                }}
                            </td>
                            <td>
                                <CheckIcon v-if="row.auto_issue" class="w-4 h-4 text-success" />
                                <span v-else class="text-base-content/30">—</span>
                            </td>
                            <td />
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="recurringInvoices.data.length > 0" class="card-body py-3">
                <Pagination :meta="meta" :links="links" />
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="row in recurringInvoices.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm cursor-pointer"
                role="button"
                tabindex="0"
                @click="goToDetail(row.id)"
                @keydown.enter="goToDetail(row.id)"
            >
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium">{{ row.name }}</p>
                            <p v-if="row.customer_display_name" class="text-sm text-base-content/60">
                                {{ row.customer_display_name }}
                            </p>
                            <p v-else class="text-sm text-base-content/40 italic">
                                {{ t('recurring_invoices.no_customer') }}
                            </p>
                        </div>
                        <RecurringStatusBadge :status="row.status" />
                    </div>
                    <div class="flex gap-2 mt-2 flex-wrap">
                        <RecurringFrequencyBadge :frequency="row.frequency" />
                        <span v-if="row.next_run_at" class="text-xs text-base-content/50">{{
                            row.next_run_at
                        }}</span>
                    </div>
                </div>
            </div>

            <Pagination v-if="recurringInvoices.data.length > 0" :meta="meta" :links="links" />
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="recurringInvoices.data.length === 0"
            :title="t('recurring_invoices.empty')"
            :description="t('recurring_invoices.empty_hint')"
            :icon="ArrowPathIcon"
        >
            <template #cta>
                <Can permission="create recurring_invoices">
                    <a href="/recurring-invoices/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('recurring_invoices.add') }}
                    </a>
                </Can>
            </template>
        </EmptyState>
    </div>
</template>
