<script setup lang="ts" generic="TRow extends object">
import { computed, ref, useSlots, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import TableFilters from './TableFilters.vue';
import TablePagination from './TablePagination.vue';
import { readSpatieQuery, visitSpatieQuery } from '@/Composables/useSpatieTableQuery';
import type { FilterConfig, Paginator, TableColumn, TableTabsConfig } from '@/types/table';

const props = withDefaults(
    defineProps<{
        columns?: TableColumn<TRow>[];
        rows: Paginator<TRow>;
        filters?: FilterConfig[];
        tabs?: TableTabsConfig;
        routeUrl?: string;
        reloadOnly?: string[];
        loading?: boolean;
        enableFilters?: boolean;
        enablePagination?: boolean;
        enableSearch?: boolean;
        searchableAttributes?: string[];
        rowKey?: (keyof TRow & string) | ((row: TRow, index: number) => string | number);
        editUrl?: (row: TRow) => string;
        deleteUrl?: (row: TRow) => string;
        canEdit?: boolean;
        canDelete?: boolean;
        canDeleteRow?: (row: TRow) => boolean;
    }>(),
    {
        columns: undefined,
        tabs: undefined,
        routeUrl: undefined,
        reloadOnly: undefined,
        rowKey: undefined,
        editUrl: undefined,
        deleteUrl: undefined,
        canDeleteRow: undefined,
        loading: false,
        enableFilters: true,
        enablePagination: true,
        enableSearch: true,
        searchableAttributes: () => [],
        filters: () => [],
        canEdit: false,
        canDelete: false,
    },
);

const slots = useSlots();
const query = ref(readSpatieQuery());
watch(
    () => props.rows,
    () => {
        query.value = readSpatieQuery();
    },
);
const popoverOpen = ref<Record<string | number, boolean>>({});

const resolvedColumns = computed<TableColumn<TRow>[]>(() => {
    if (props.columns?.length) return props.columns;
    const first = props.rows?.data?.[0] as Record<string, unknown> | undefined;
    if (!first) return [];
    return Object.keys(first).map((key) => ({
        key,
        label: key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        sortable: true,
    }));
});

const showActionsColumn = computed(
    () => (props.canEdit && !!props.editUrl) || (props.canDelete && !!props.deleteUrl) || !!slots.buttons,
);

const skeletonCount = computed(() => Math.min(props.rows?.per_page ?? 5, 5));

function rowIdentifier(row: TRow, index: number): string | number {
    if (typeof props.rowKey === 'function') return props.rowKey(row, index);
    if (typeof props.rowKey === 'string') return String((row as Record<string, unknown>)[props.rowKey] ?? index);
    return index;
}

function cellValue(row: TRow, column: TableColumn<TRow>) {
    return column.value ? column.value(row) : (row as Record<string, unknown>)[column.key];
}

function cellClass(row: TRow, column: TableColumn<TRow>) {
    if (typeof column.cellClass === 'function') return column.cellClass(row);
    return column.cellClass ?? column.class;
}

function currentSortDirection(column: TableColumn<TRow>): 'asc' | 'desc' | null {
    if (!column.sortable) return null;
    if (query.value.sort === column.key) return 'asc';
    if (query.value.sort === `-${column.key}`) return 'desc';
    return null;
}

function sortBy(column: TableColumn<TRow>) {
    if (!column.sortable) return;
    const current = currentSortDirection(column);
    const sort = current === null ? column.key : current === 'asc' ? `-${column.key}` : null;
    visitSpatieQuery({ sort }, { routeUrl: props.routeUrl, only: props.reloadOnly });
}

function changeFilter(property: string, value: string | null) {
    visitSpatieQuery({ [`filter[${property}]`]: value }, { routeUrl: props.routeUrl, only: props.reloadOnly });
}

function clearFilters() {
    const changes: Record<string, null> = { 'filter[search]': null };
    Object.keys(query.value.filters).forEach((key) => {
        const filter = props.filters.find((f) => f.property === key);
        if (key === 'search' || filter?.clearable !== false) changes[`filter[${key}]`] = null;
    });
    visitSpatieQuery(changes, { routeUrl: props.routeUrl, only: props.reloadOnly });
}

function changePerPage(perPage: number) {
    visitSpatieQuery(
        { per_page: perPage, page: 1 },
        { routeUrl: props.routeUrl, only: props.reloadOnly, resetPage: false },
    );
}

function changePage(page: number) {
    visitSpatieQuery({ page }, { routeUrl: props.routeUrl, only: props.reloadOnly, resetPage: false });
}

function openDeletePopover(id: string | number) {
    popoverOpen.value = { [id]: true };
}

function closeDeletePopover(id: string | number) {
    popoverOpen.value[id] = false;
}

function canDeleteThisRow(row: TRow): boolean {
    return props.canDelete && !!props.deleteUrl && (props.canDeleteRow ? props.canDeleteRow(row) : true);
}

function confirmDelete(row: TRow, id: string | number) {
    if (!props.deleteUrl) return;
    closeDeletePopover(id);
    router.delete(props.deleteUrl(row), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <TableFilters
            v-if="enableFilters"
            :filters="filters"
            :tabs="tabs"
            :query-filters="query.filters"
            :disable-search="!enableSearch"
            :searchable-attributes="searchableAttributes"
            @change="changeFilter"
            @clear="clearFilters"
        />

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th
                            v-for="column in resolvedColumns"
                            :key="column.key"
                            :class="[
                                column.headerClass ?? column.class,
                                column.sortable ? 'cursor-pointer select-none' : '',
                            ]"
                            @click="sortBy(column)"
                        >
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                {{ column.label }}
                                <template v-if="column.sortable">
                                    <span v-if="currentSortDirection(column) === 'asc'" class="text-xs">▲</span>
                                    <span v-else-if="currentSortDirection(column) === 'desc'" class="text-xs">▼</span>
                                    <span v-else class="text-xs opacity-30">▲</span>
                                </template>
                            </span>
                        </th>
                        <th v-if="showActionsColumn" class="w-0" />
                    </tr>
                </thead>

                <tbody>
                    <template v-if="loading">
                        <tr v-for="i in skeletonCount" :key="`skeleton-${i}`">
                            <td v-for="column in resolvedColumns" :key="column.key">
                                <div class="skeleton h-4 w-full" />
                            </td>
                            <td v-if="showActionsColumn"><div class="skeleton h-6 w-16 ml-auto" /></td>
                        </tr>
                    </template>

                    <template v-else>
                        <template v-for="(row, index) in rows?.data ?? []" :key="rowIdentifier(row, index)">
                            <tr class="group">
                                <td v-for="column in resolvedColumns" :key="column.key" :class="cellClass(row, column)">
                                    <slot :name="`cell-${column.key}`" :row="row" :value="cellValue(row, column)">
                                        {{ cellValue(row, column) }}
                                    </slot>
                                </td>

                                <td v-if="showActionsColumn">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <slot name="buttons" :row="row" :index="index" />

                                        <a
                                            v-if="canEdit && editUrl"
                                            :href="editUrl(row)"
                                            class="btn btn-ghost btn-xs"
                                            :title="$t('edit')"
                                            >✎</a
                                        >

                                        <div v-if="canDeleteThisRow(row)" class="relative">
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-xs text-error"
                                                :title="$t('delete')"
                                                @click="openDeletePopover(rowIdentifier(row, index))"
                                            >
                                                🗑
                                            </button>
                                            <div
                                                v-if="popoverOpen[rowIdentifier(row, index)]"
                                                class="absolute right-0 bottom-full z-50 mb-1 flex w-44 flex-col gap-2 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg"
                                            >
                                                <p class="text-center text-sm font-medium">
                                                    {{ $t('confirm_delete') }}
                                                </p>
                                                <div class="flex gap-2">
                                                    <button
                                                        type="button"
                                                        class="btn btn-ghost btn-sm flex-1"
                                                        @click="closeDeletePopover(rowIdentifier(row, index))"
                                                    >
                                                        {{ $t('no') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-error btn-sm flex-1"
                                                        @click="confirmDelete(row, rowIdentifier(row, index))"
                                                    >
                                                        {{ $t('yes') }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="$slots['row-details']">
                                <td :colspan="resolvedColumns.length + (showActionsColumn ? 1 : 0)">
                                    <slot name="row-details" :row="row" :index="index" />
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!rows?.data?.length">
                            <td
                                :colspan="resolvedColumns.length + (showActionsColumn ? 1 : 0)"
                                class="py-8 text-center text-base-content/50"
                            >
                                {{ Object.keys(query.filters).length ? $t('no_records_filters') : $t('no_results') }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <TablePagination
            v-if="enablePagination"
            :rows="rows as Paginator<unknown>"
            @page="changePage"
            @per-page="changePerPage"
        />
    </div>
</template>
