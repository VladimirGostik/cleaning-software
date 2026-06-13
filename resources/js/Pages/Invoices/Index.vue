<script setup lang="ts">
    import { computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, ReceiptPercentIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
    import Pagination from '@/Components/Pagination.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useInvoiceFilters } from '@/Composables/useInvoiceFilters';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        invoices: PaginatedData<App.Data.Invoices.InvoiceListItemData>;
        filters: {
            search?: string | null;
            status?: App.Enums.InvoiceStatusEnum | null;
            type?: App.Enums.InvoiceTypeEnum | null;
        };
        statusOptions: Array<{ value: string; label: string }>;
        typeOptions: Array<{ value: string; label: string }>;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useInvoiceFilters(props.filters);

    const meta = computed(() => props.invoices.meta);
    const links = computed(() => props.invoices.links);

    const subtitle = computed(() =>
        t('invoices.subtitle').replace('{count}', String(props.invoices.meta.total)),
    );

    function goToDetail(id: string) {
        router.get(`/invoices/${id}`);
    }

    function isOverdue(row: App.Data.Invoices.InvoiceListItemData): boolean {
        return row.status === 'overdue';
    }
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('invoices.title')" :subtitle="subtitle">
            <template #actions>
                <Can permission="create invoices" feature="invoices">
                    <a href="/invoices/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('invoices.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
            <input
                v-model="filterState.search"
                type="search"
                :placeholder="t('invoices.search_placeholder')"
                class="input input-bordered input-sm w-full max-w-xs"
            />
            <select v-model="filterState.status" class="select select-bordered select-sm">
                <option :value="undefined">{{ t('invoices.filter.all_statuses') }}</option>
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filterState.type" class="select select-bordered select-sm">
                <option :value="undefined">{{ t('invoices.filter.all_types') }}</option>
                <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
        </div>

        <!-- Desktop table -->
        <div class="hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('invoices.col.number') }}</th>
                            <th>{{ t('invoices.col.customer') }}</th>
                            <th>{{ t('invoices.col.type') }}</th>
                            <th>{{ t('invoices.col.status') }}</th>
                            <th class="text-right">{{ t('invoices.col.total') }}</th>
                            <th>{{ t('invoices.col.issue_date') }}</th>
                            <th>{{ t('invoices.col.due_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in invoices.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            :class="{ 'bg-error/5': isOverdue(row) }"
                            @click="goToDetail(row.id)"
                        >
                            <td>
                                <span class="font-medium font-mono text-sm">
                                    {{ row.number ?? t('invoices.draft_number') }}
                                </span>
                            </td>
                            <td>{{ row.customer_name }}</td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    {{ t('invoice_type.' + row.type) }}
                                </span>
                            </td>
                            <td>
                                <InvoiceStatusBadge :status="row.status" />
                            </td>
                            <td class="text-right font-medium">{{ row.total }}</td>
                            <td>{{ row.issue_date }}</td>
                            <td :class="{ 'text-error font-medium': isOverdue(row) }">
                                {{ row.due_date }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="row in invoices.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm cursor-pointer"
                :class="{ 'border border-error/30': isOverdue(row) }"
                @click="goToDetail(row.id)"
            >
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium font-mono text-sm">
                                {{ row.number ?? t('invoices.draft_number') }}
                            </p>
                            <p class="text-sm">{{ row.customer_name }}</p>
                        </div>
                        <InvoiceStatusBadge :status="row.status" />
                    </div>
                    <div class="flex justify-between text-sm mt-2">
                        <span class="text-base-content/60">{{ row.due_date }}</span>
                        <span class="font-semibold">{{ row.total }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="invoices.data.length === 0"
            :title="t('invoices.empty')"
            :description="t('invoices.empty_hint')"
            :icon="ReceiptPercentIcon"
        >
            <template #cta>
                <Can permission="create invoices" feature="invoices">
                    <a href="/invoices/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('invoices.add') }}
                    </a>
                </Can>
            </template>
        </EmptyState>

        <!-- Pagination -->
        <Pagination v-if="invoices.data.length > 0" :meta="meta" :links="links" />
    </div>
</template>
