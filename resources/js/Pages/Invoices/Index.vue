<script setup lang="ts">
    import { computed, ref, watch, nextTick } from 'vue';
    import { router } from '@inertiajs/vue3';
    import {
        PlusIcon,
        ReceiptPercentIcon,
        ChevronRightIcon,
        Cog6ToothIcon,
        ExclamationTriangleIcon,
        ClockIcon,
        SparklesIcon,
        ArrowDownTrayIcon,
        FunnelIcon,
        EllipsisVerticalIcon,
        XMarkIcon,
    } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
    import InvoiceSettingsDrawer from '@/Components/Invoices/InvoiceSettingsDrawer.vue';
    import InvoiceQuickPeekDrawer from '@/Components/Invoices/InvoiceQuickPeekDrawer.vue';
    import InvoicesTabBar from '@/Components/Invoices/InvoicesTabBar.vue';
    import Pagination from '@/Components/Pagination.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useInvoiceFilters, type InvoiceTab } from '@/Composables/useInvoiceFilters';
    import type { PaginatedData } from '@/types/pagination';

    interface TabCounts {
        all?: number | null;
        all_issued?: number | null;
        recurring?: number | null;
        drafts?: number | null;
        overdue?: number | null;
    }

    interface Props {
        invoices: PaginatedData<App.Data.Invoices.InvoiceListItemData>;
        filters: {
            search?: string | null;
            status?: App.Enums.InvoiceStatusEnum | null;
            type?: App.Enums.InvoiceTypeEnum | null;
            month?: string | null;
            tab?: InvoiceTab | null;
            issued_from?: string | null;
            issued_to?: string | null;
            due_from?: string | null;
            due_to?: string | null;
            total_min?: string | null;
            total_max?: string | null;
            client_id?: string | null;
        };
        statusOptions: Array<{ value: string; label: string }>;
        typeOptions: Array<{ value: string; label: string }>;
        tabCounts?: TabCounts;
        invoiceSettings: App.Data.Invoices.InvoiceSettingsData;
        settingsTemplateOptions: Array<{ value: string; label: string }>;
        settingsCompanyName: string;
        settingsIsVatPayer: boolean;
        nextNumberPreview?: string | null;
        invoiceStats: App.Data.Invoices.InvoiceStatsData;
        clients: App.Data.Clients.ClientOptionData[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useInvoiceFilters(props.filters);

    const meta = computed(() => props.invoices.meta);
    const links = computed(() => props.invoices.links);

    const resolvedTab = computed<InvoiceTab>(() => props.filters.tab ?? 'all');

    // eslint-disable-next-line no-restricted-syntax -- drawer open state, imperative UI toggle
    const settingsOpen = ref(false);

    // eslint-disable-next-line no-restricted-syntax -- advanced filter modal open state, imperative UI toggle
    const advancedOpen = ref(false);

    // eslint-disable-next-line no-restricted-syntax -- peek drawer open state
    const peekOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- selected row for peek
    const peekInvoice = ref<App.Data.Invoices.InvoiceListItemData | null>(null);

    // eslint-disable-next-line no-restricted-syntax -- multi-select state, imperative, no cross-component share
    const selectedIds = ref<string[]>([]);

    // eslint-disable-next-line no-restricted-syntax -- filter modal first input ref for focus management
    const advancedFirstInput = ref<HTMLInputElement | null>(null);

    watch(advancedOpen, (val) => {
        if (val) nextTick(() => advancedFirstInput.value?.focus());
    });

    // Reset selection when page changes
    watch(() => props.invoices, () => {
        selectedIds.value = [];
    });

    const subtitle = computed(() =>
        t('invoices.subtitle').replace('{count}', String(props.invoices.meta.total)),
    );

    const statCards = computed(() => [
        {
            key: 'issued',
            label: t('invoices.stats.issued_this_month'),
            value: props.invoiceStats.issued_this_month.amount,
            count: props.invoiceStats.issued_this_month.count,
            icon: ReceiptPercentIcon,
        },
        {
            key: 'overdue',
            label: t('invoices.stats.overdue'),
            value: props.invoiceStats.overdue.amount,
            count: props.invoiceStats.overdue.count,
            icon: ExclamationTriangleIcon,
        },
        {
            key: 'pending',
            label: t('invoices.stats.pending'),
            value: props.invoiceStats.pending.amount,
            count: props.invoiceStats.pending.count,
            icon: ClockIcon,
        },
        {
            key: 'recurring',
            label: t('invoices.stats.recurring_monthly'),
            value: props.invoiceStats.recurring_monthly.amount,
            count: props.invoiceStats.recurring_monthly.count,
            icon: SparklesIcon,
        },
    ]);

    const monthOptions = computed(() => {
        const opts: Array<{ value: string; label: string }> = [];
        const now = new Date();
        for (let i = 0; i < 12; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const label = d.toLocaleDateString('sk-SK', { year: 'numeric', month: 'long' });
            opts.push({ value, label });
        }
        return opts;
    });

    const monthSelectOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('invoices.filter.all_months') },
        ...monthOptions.value.map((o) => ({ value: o.value, label: o.label })),
    ]);

    const clientOptions = computed<SelectOption[]>(() => [
        { value: '', label: t('invoices.filter.client_all') },
        ...props.clients.map((c) => ({ value: c.id, label: c.name })),
    ]);

    const allSelected = computed(
        () => props.invoices.data.length > 0 && selectedIds.value.length === props.invoices.data.length,
    );

    function goToDetail(id: string) {
        router.get(`/invoices/${id}`);
    }

    function openPeek(row: App.Data.Invoices.InvoiceListItemData) {
        peekInvoice.value = row;
        peekOpen.value = true;
    }

    function isOverdue(row: App.Data.Invoices.InvoiceListItemData): boolean {
        return row.status === 'overdue';
    }

    function toggleStatus(value: string): void {
        filterState.status =
            filterState.status === value ? undefined : (value as App.Enums.InvoiceStatusEnum);
    }

    function toggleType(value: string): void {
        filterState.type =
            filterState.type === value ? undefined : (value as App.Enums.InvoiceTypeEnum);
    }

    function clearAdvancedFilters() {
        filterState.issued_from = undefined;
        filterState.issued_to = undefined;
        filterState.due_from = undefined;
        filterState.due_to = undefined;
        filterState.total_min = null;
        filterState.total_max = null;
        filterState.client_id = undefined;
        filterState.month = undefined;
    }

    function rowAction(routeName: string, id: string) {
        router.post(
            `/invoices/${id}/${routeName.replace('invoices.', '')}`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => router.reload({ only: ['invoices', 'invoiceStats', 'tabCounts'] }),
            },
        );
    }

    function handlePeekAction(routeName: string, id: string) {
        peekOpen.value = false;
        rowAction(routeName, id);
    }

    function cancelRow(id: string) {
        if (window.confirm(t('invoices.cancel_confirm'))) {
            rowAction('invoices.cancel', id);
        }
    }

    function exportInvoices() {
        const params = new URLSearchParams();
        if (filterState.search) params.set('search', filterState.search);
        if (filterState.status) params.set('status', filterState.status);
        if (filterState.type) params.set('type', filterState.type);
        if (filterState.month) params.set('month', filterState.month);
        if (filterState.tab) params.set('tab', filterState.tab);
        if (filterState.issued_from) params.set('issued_from', filterState.issued_from);
        if (filterState.issued_to) params.set('issued_to', filterState.issued_to);
        if (filterState.due_from) params.set('due_from', filterState.due_from);
        if (filterState.due_to) params.set('due_to', filterState.due_to);
        if (filterState.total_min != null) params.set('total_min', String(filterState.total_min));
        if (filterState.total_max != null) params.set('total_max', String(filterState.total_max));
        if (filterState.client_id) params.set('client_id', filterState.client_id);
        window.location.href = `/invoices/export?${params.toString()}`;
    }

    function toggleAll() {
        selectedIds.value = allSelected.value ? [] : props.invoices.data.map((r) => r.id);
    }

    function toggleOne(id: string) {
        selectedIds.value = selectedIds.value.includes(id)
            ? selectedIds.value.filter((x) => x !== id)
            : [...selectedIds.value, id];
    }

    function bulkMarkPaid() {
        router.post(
            '/invoices/bulk',
            { action: 'mark_paid', ids: selectedIds.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = [];
                    router.reload({ only: ['invoices', 'invoiceStats', 'tabCounts'] });
                },
            },
        );
    }

    // Bind month SelectInput which emits string | number
    const monthSelectValue = computed({
        get: () => filterState.month ?? '',
        set: (val: string | number) => {
            filterState.month = val === '' ? undefined : String(val);
        },
    });

    const clientSelectValue = computed({
        get: () => filterState.client_id ?? '',
        set: (val: string | number) => {
            filterState.client_id = val === '' ? undefined : String(val);
        },
    });
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('invoices.title')" :subtitle="subtitle">
            <template #actions>
                <Can permission="manage billing settings" feature="invoices">
                    <button type="button" class="btn btn-outline btn-sm" @click="settingsOpen = true">
                        <Cog6ToothIcon class="w-4 h-4" />{{ t('invoice_settings.open') }}
                    </button>
                </Can>
                <Can permission="view invoices" feature="invoices">
                    <button type="button" class="btn btn-outline btn-sm" @click="exportInvoices">
                        <ArrowDownTrayIcon class="w-4 h-4" />
                        {{ t('invoices.export') }}
                    </button>
                </Can>
                <Can permission="create recurring_invoices" feature="invoices">
                    <a href="/recurring-invoices/create" class="btn btn-outline btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('invoices.add_recurring') }}
                    </a>
                </Can>
                <Can permission="create invoices" feature="invoices">
                    <a href="/invoices/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('invoices.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <!-- Stats cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div v-for="card in statCards" :key="card.key" class="card bg-base-100 border border-base-300 border-b-2 border-b-primary shadow-sm">
                <div class="card-body p-5">
                    <div class="flex items-start justify-between mb-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ card.label }}</span>
                        <span class="rounded-lg p-1.5 bg-primary/10 text-primary">
                            <component :is="card.icon" class="w-4 h-4" />
                        </span>
                    </div>
                    <div class="text-2xl font-bold text-primary">{{ card.value }} €</div>
                    <div class="text-xs text-base-content/50 mt-1">{{ t('invoices.stats.count').replace('{count}', String(card.count)) }}</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <InvoicesTabBar :active="resolvedTab" :counts="tabCounts" />

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <!-- Search -->
            <input
                v-model="filterState.search"
                type="search"
                :placeholder="t('invoices.search_placeholder')"
                class="input input-bordered input-sm w-full max-w-xs"
            />
            <!-- Status pills -->
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    type="button"
                    :aria-pressed="filterState.status === opt.value"
                    :class="['btn btn-xs', filterState.status === opt.value ? 'btn-primary' : 'btn-ghost']"
                    @click="toggleStatus(opt.value)"
                >{{ opt.label }}</button>
            </div>
            <!-- Type pills -->
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    type="button"
                    :aria-pressed="filterState.type === opt.value"
                    :class="['btn btn-xs', filterState.type === opt.value ? 'btn-primary' : 'btn-ghost']"
                    @click="toggleType(opt.value)"
                >{{ opt.label }}</button>
            </div>
            <!-- Advanced filter toggle -->
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square ml-auto cursor-pointer"
                :aria-label="t('invoices.filter.advanced')"
                @click="advancedOpen = true"
            >
                <FunnelIcon class="w-4 h-4" />
            </button>
        </div>

        <!-- Desktop table wrapped in card -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th class="w-8">
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    :checked="allSelected"
                                    :aria-label="t('invoices.select_all')"
                                    @change="toggleAll"
                                />
                            </th>
                            <th>{{ t('invoices.col.number') }}</th>
                            <th>{{ t('invoices.col.customer') }}</th>
                            <th>{{ t('invoices.col.type') }}</th>
                            <th>{{ t('invoices.col.issue_date') }}</th>
                            <th>{{ t('invoices.col.due_date') }}</th>
                            <th class="text-right">{{ t('invoices.col.total') }}</th>
                            <th>{{ t('invoices.col.status') }}</th>
                            <th class="w-16" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in invoices.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            :class="{ 'bg-error/5': isOverdue(row) }"
                            role="button"
                            tabindex="0"
                            @click="openPeek(row)"
                            @keydown.enter="openPeek(row)"
                        >
                            <td>
                                <input
                                    type="checkbox"
                                    class="checkbox checkbox-sm"
                                    :checked="selectedIds.includes(row.id)"
                                    :aria-label="t('invoices.select_row')"
                                    @click.stop
                                    @change="toggleOne(row.id)"
                                />
                            </td>
                            <td>
                                <span class="font-medium font-mono text-sm">
                                    {{ row.number ?? t('invoices.draft_number') }}
                                </span>
                            </td>
                            <td>
                                <div class="font-medium">{{ row.customer_name }}</div>
                                <div v-if="row.object_name" class="text-xs text-base-content/50">{{ row.object_name }}</div>
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    {{ t('invoice_type.' + row.type) }}
                                </span>
                            </td>
                            <td class="text-sm">{{ row.issue_date }}</td>
                            <td
                                class="text-sm"
                                :class="{ 'text-error font-medium': isOverdue(row) }"
                            >
                                {{ row.due_date }}
                            </td>
                            <td class="text-right font-mono font-medium">{{ row.total }}</td>
                            <td>
                                <InvoiceStatusBadge :status="row.status" />
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <ChevronRightIcon class="w-4 h-4 text-base-content/40" />
                                    <!-- Row dropdown actions -->
                                    <Can permission="edit invoices" feature="invoices">
                                        <details
                                            v-if="row.status === 'draft' || row.status === 'issued' || row.status === 'overdue'"
                                            class="dropdown dropdown-end"
                                            @click.stop
                                        >
                                            <summary class="btn btn-ghost btn-xs btn-square list-none" :aria-label="t('invoices.col.actions')">
                                                <EllipsisVerticalIcon class="w-4 h-4" />
                                            </summary>
                                            <ul class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg z-10 w-40 p-1">
                                                <li v-if="row.status === 'draft'">
                                                    <button type="button" @click="rowAction('invoices.issue', row.id)">
                                                        {{ t('invoices.action.issue') }}
                                                    </button>
                                                </li>
                                                <li v-if="row.status === 'issued'">
                                                    <button type="button" @click="rowAction('invoices.pay', row.id)">
                                                        {{ t('invoices.action.mark_paid') }}
                                                    </button>
                                                </li>
                                                <li v-if="row.status === 'issued' || row.status === 'overdue'">
                                                    <button type="button" class="text-error" @click="cancelRow(row.id)">
                                                        {{ t('invoices.action.cancel') }}
                                                    </button>
                                                </li>
                                            </ul>
                                        </details>
                                    </Can>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination inside card footer -->
            <div v-if="invoices.data.length > 0" class="card-body py-3">
                <Pagination :meta="meta" :links="links" />
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            <div
                v-for="row in invoices.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm cursor-pointer"
                :class="{ 'border border-error/30': isOverdue(row) }"
                role="button"
                tabindex="0"
                @click="openPeek(row)"
                @keydown.enter="openPeek(row)"
            >
                <div class="card-body p-4">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-2">
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm mt-0.5"
                                :checked="selectedIds.includes(row.id)"
                                :aria-label="t('invoices.select_row')"
                                @click.stop
                                @change="toggleOne(row.id)"
                            />
                            <div>
                                <p class="font-medium font-mono text-sm">
                                    {{ row.number ?? t('invoices.draft_number') }}
                                </p>
                                <p class="text-sm">{{ row.customer_name }}</p>
                                <p v-if="row.object_name" class="text-xs text-base-content/50">{{ row.object_name }}</p>
                            </div>
                        </div>
                        <InvoiceStatusBadge :status="row.status" />
                    </div>
                    <div class="flex justify-between text-sm mt-2">
                        <span class="text-base-content/60">{{ row.due_date }}</span>
                        <span class="font-mono font-semibold">{{ row.total }}</span>
                    </div>
                </div>
            </div>

            <!-- Mobile pagination -->
            <Pagination v-if="invoices.data.length > 0" :meta="meta" :links="links" />
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

        <!-- Bulk action floating bar -->
        <div
            v-if="selectedIds.length > 0"
            class="fixed bottom-0 inset-x-0 z-30 bg-base-100 border-t border-base-300 shadow-lg"
        >
            <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-3">
                <span class="text-sm font-medium flex-1">
                    {{ t('invoices.bulk.selected').replace('{count}', String(selectedIds.length)) }}
                </span>
                <button type="button" class="btn btn-ghost btn-sm" @click="selectedIds = []">
                    <XMarkIcon class="w-4 h-4" />
                    {{ t('invoices.filter.clear') }}
                </button>
                <Can permission="edit invoices" feature="invoices">
                    <button type="button" class="btn btn-primary btn-sm" @click="bulkMarkPaid">
                        {{ t('invoices.bulk.mark_paid') }}
                    </button>
                </Can>
            </div>
        </div>

        <InvoiceSettingsDrawer
            v-model:open="settingsOpen"
            :settings="invoiceSettings"
            :template-options="settingsTemplateOptions"
            :company-name="settingsCompanyName"
            :is-vat-payer="settingsIsVatPayer"
            :next-number-preview="nextNumberPreview"
        />

        <InvoiceQuickPeekDrawer
            v-model:open="peekOpen"
            :invoice="peekInvoice"
            @open-detail="goToDetail"
            @action="handlePeekAction"
        />

        <!-- Advanced filter modal -->
        <Teleport to="body">
            <template v-if="advancedOpen">
                <div class="fixed inset-0 bg-black/40 z-40" @click="advancedOpen = false" />
                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('invoices.filter.advanced')"
                    class="fixed z-50 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg"
                    @keydown.escape="advancedOpen = false"
                >
                    <div class="modal-box max-w-lg w-full">
                        <h3 class="text-lg font-semibold mb-4">{{ t('invoices.filter.advanced') }}</h3>

                        <div class="space-y-4">
                            <!-- Client -->
                            <SelectInput
                                v-model="clientSelectValue"
                                :options="clientOptions"
                                :label="t('invoices.filter.client')"
                            />

                            <!-- Month -->
                            <SelectInput
                                v-model="monthSelectValue"
                                :options="monthSelectOptions"
                                :label="t('invoices.filter.all_months')"
                            />

                            <!-- Issue date range -->
                            <div class="grid grid-cols-2 gap-3">
                                <label class="form-control w-full">
                                    <div class="label"><span class="label-text">{{ t('invoices.filter.issued_from') }}</span></div>
                                    <input
                                        ref="advancedFirstInput"
                                        v-model="filterState.issued_from"
                                        type="date"
                                        class="input input-bordered input-sm"
                                    />
                                </label>
                                <label class="form-control w-full">
                                    <div class="label"><span class="label-text">{{ t('invoices.filter.issued_to') }}</span></div>
                                    <input
                                        v-model="filterState.issued_to"
                                        type="date"
                                        class="input input-bordered input-sm"
                                    />
                                </label>
                            </div>

                            <!-- Due date range -->
                            <div class="grid grid-cols-2 gap-3">
                                <label class="form-control w-full">
                                    <div class="label"><span class="label-text">{{ t('invoices.filter.due_from') }}</span></div>
                                    <input
                                        v-model="filterState.due_from"
                                        type="date"
                                        class="input input-bordered input-sm"
                                    />
                                </label>
                                <label class="form-control w-full">
                                    <div class="label"><span class="label-text">{{ t('invoices.filter.due_to') }}</span></div>
                                    <input
                                        v-model="filterState.due_to"
                                        type="date"
                                        class="input input-bordered input-sm"
                                    />
                                </label>
                            </div>

                            <!-- Amount range -->
                            <div class="grid grid-cols-2 gap-3">
                                <NumberInput
                                    v-model="filterState.total_min"
                                    :label="t('invoices.filter.amount_min')"
                                    :min="0"
                                    :step="0.01"
                                />
                                <NumberInput
                                    v-model="filterState.total_max"
                                    :label="t('invoices.filter.amount_max')"
                                    :min="0"
                                    :step="0.01"
                                />
                            </div>
                        </div>

                        <div class="modal-action">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                @click="clearAdvancedFilters"
                            >
                                {{ t('invoices.filter.clear') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="advancedOpen = false"
                            >
                                {{ t('invoices.filter.apply') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </Teleport>
    </div>
</template>
