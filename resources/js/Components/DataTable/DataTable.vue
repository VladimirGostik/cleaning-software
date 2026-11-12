<script setup lang="ts" generic="TRow extends object">
import { computed, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import TableRowActions from './TableRowActions.vue';
import TableFilters from './TableFilters.vue';
import TableHeaderFilter from './TableHeaderFilter.vue';
import TablePagination from './TablePagination.vue';
import { useRowLink } from '@/Composables/useRowLink';
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
        rowHref?: (row: TRow) => string | null;
        openRow?: (row: TRow) => void;
        selectable?: boolean;
        selectLabel?: (row: TRow) => string;
        selectAllLabel?: string;
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
        rowHref: undefined,
        openRow: undefined,
        selectLabel: undefined,
        selectAllLabel: undefined,
        selectable: false,
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

const selected = defineModel<string[]>('selected', { default: () => [] });
const slots = useSlots();
const query = ref(readSpatieQuery());
watch(
    () => props.rows,
    () => {
        query.value = readSpatieQuery();
        if (props.selectable && selected.value.length) selected.value = [];
    },
);

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

const pageIds = computed(() => (props.rows?.data ?? []).map((row, index) => String(rowIdentifier(row, index))));
const allSelected = computed(
    () => pageIds.value.length > 0 && pageIds.value.every((id) => selected.value.includes(id)),
);
const someSelected = computed(() => selected.value.length > 0 && !allSelected.value);
const viewportWidth = ref(typeof window === 'undefined' ? 1920 : window.innerWidth);
const updateViewport = () => {
    viewportWidth.value = window.innerWidth;
};
onMounted(() => window.addEventListener('resize', updateViewport));
onBeforeUnmount(() => window.removeEventListener('resize', updateViewport));

const columnCount = computed(
    () =>
        resolvedColumns.value.filter((column) => !column.hideBelow || viewportWidth.value >= column.hideBelow).length +
        (showActionsColumn.value ? 1 : 0) +
        (props.selectable ? 1 : 0),
);

if (import.meta.env.DEV && props.selectable && !props.rowKey) {
    console.warn('DataTable: `selectable` requires `rowKey`.');
}

if (import.meta.env.DEV && props.selectable && !props.selectAllLabel) {
    console.warn('DataTable: `selectable` requires `selectAllLabel`.');
}

function toggleAll(checked: boolean) {
    selected.value = checked ? [...pageIds.value] : [];
}

function toggleRow(id: string, checked: boolean) {
    const rest = selected.value.filter((value) => value !== id);
    selected.value = checked ? pageIds.value.filter((value) => value === id || rest.includes(value)) : rest;
}

function alignClass(column: TableColumn<TRow>): string {
    return column.align === 'right' ? 'text-right tabular-nums' : '';
}

const HIDE_BELOW_CLASS = {
    1440: 'hidden min-[1440px]:table-cell',
    1600: 'hidden min-[1600px]:table-cell',
} as const;

function widthClass(column: TableColumn<TRow>): string {
    const hide = column.hideBelow ? HIDE_BELOW_CLASS[column.hideBelow] : '';
    if (column.primary) return `min-w-40 ${hide}`;
    return `${column.nowrap ? 'whitespace-nowrap' : ''} ${hide}`;
}

function cellValue(row: TRow, column: TableColumn<TRow>) {
    return column.value ? column.value(row) : (row as Record<string, unknown>)[column.key];
}

function cellClass(row: TRow, column: TableColumn<TRow>) {
    if (typeof column.cellClass === 'function') return column.cellClass(row);
    return column.cellClass ?? column.class;
}

const cardColumns = computed(() => resolvedColumns.value.filter((column) => !column.hideBelow));
const cardTitleColumn = computed(() => cardColumns.value.find((column) => column.primary) ?? cardColumns.value[0]);
const cardMetaColumns = computed(() => cardColumns.value.filter((column) => column !== cardTitleColumn.value));

function hasCell(row: TRow, column: TableColumn<TRow>): boolean {
    if (slots[`cell-${column.key}`]) return true;
    const value = cellValue(row, column);
    return value !== null && value !== undefined && value !== '';
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

function headerFilter(column: TableColumn<TRow>): FilterConfig | undefined {
    return column.filter ? props.filters.find((filter) => filter.property === column.filter) : undefined;
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

function canDeleteThisRow(row: TRow): boolean {
    return props.canDelete && !!props.deleteUrl && (props.canDeleteRow ? props.canDeleteRow(row) : true);
}

function resolveRowHref(row: TRow): string | null {
    return props.rowHref ? props.rowHref(row) : null;
}

const rowLink = useRowLink();

function onRowClick(row: TRow, event: MouseEvent) {
    rowLink.onClick(resolveRowHref(row), event, props.openRow ? () => props.openRow?.(row) : undefined);
}

function onRowAuxClick(row: TRow, event: MouseEvent) {
    rowLink.onAuxClick(resolveRowHref(row), event);
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

        <label
            v-if="selectable"
            class="flex min-h-11 cursor-pointer items-center gap-3 px-3 text-sm md:hidden"
            data-autom="select-all-mobile"
        >
            <input
                type="checkbox"
                class="checkbox"
                :checked="allSelected"
                :indeterminate="someSelected"
                @change="toggleAll(($event.target as HTMLInputElement).checked)"
            />
            <span>{{ selectAllLabel }}</span>
        </label>

        <div class="kg-list md:hidden">
            <template v-if="loading">
                <div v-for="i in skeletonCount" :key="`card-skeleton-${i}`" class="kg-list-row">
                    <div class="skeleton h-4 w-full" />
                </div>
            </template>

            <template v-else>
                <div
                    v-for="(row, index) in rows?.data ?? []"
                    :key="rowIdentifier(row, index)"
                    class="kg-list-row"
                    :class="rowLink.rowClass(resolveRowHref(row))"
                    @click="onRowClick(row, $event)"
                    @auxclick="onRowAuxClick(row, $event)"
                >
                    <template v-if="$slots.card">
                        <div class="min-w-0 flex-1">
                            <slot name="card" :row="row" :index="index" />
                        </div>
                    </template>

                    <template v-else>
                        <label
                            v-if="selectable"
                            class="-ml-3 flex size-11 shrink-0 cursor-pointer items-center justify-center self-start"
                            @click.stop
                        >
                            <input
                                type="checkbox"
                                class="checkbox"
                                :checked="selected.includes(String(rowIdentifier(row, index)))"
                                :aria-label="selectLabel?.(row)"
                                @change="
                                    toggleRow(
                                        String(rowIdentifier(row, index)),
                                        ($event.target as HTMLInputElement).checked,
                                    )
                                "
                            />
                        </label>
                        <div class="flex min-w-0 flex-1 flex-col gap-1 py-0.5">
                            <div class="flex flex-wrap items-start gap-x-2 gap-y-1">
                                <div
                                    v-if="cardTitleColumn"
                                    class="flex min-w-32 flex-1 items-center text-base"
                                    :class="{ 'min-h-11': showActionsColumn }"
                                >
                                    <div class="min-w-0 [overflow-wrap:anywhere]">
                                        <slot
                                            :name="`cell-${cardTitleColumn.key}`"
                                            :row="row"
                                            :value="cellValue(row, cardTitleColumn)"
                                        >
                                            {{ cellValue(row, cardTitleColumn) }}
                                        </slot>
                                    </div>
                                </div>
                                <TableRowActions
                                    v-if="showActionsColumn"
                                    class="ml-auto shrink-0"
                                    :edit-href="canEdit && editUrl ? editUrl(row) : null"
                                    :delete-href="canDeleteThisRow(row) && deleteUrl ? deleteUrl(row) : null"
                                    @click.stop
                                >
                                    <slot name="buttons" :row="row" :index="index" />
                                </TableRowActions>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                <template v-for="column in cardMetaColumns" :key="column.key">
                                    <span
                                        v-if="hasCell(row, column)"
                                        class="inline-flex max-w-full items-baseline gap-1.5"
                                    >
                                        <span class="whitespace-nowrap">{{ column.label }}</span>
                                        <span class="min-w-0 text-base-content [overflow-wrap:anywhere]">
                                            <slot
                                                :name="`cell-${column.key}`"
                                                :row="row"
                                                :value="cellValue(row, column)"
                                            >
                                                {{ cellValue(row, column) }}
                                            </slot>
                                        </span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <p v-if="!loading && !rows?.data?.length" class="py-8 text-center text-muted">
                {{ Object.keys(query.filters).length ? $t('no_records_filters') : $t('no_results') }}
            </p>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th v-if="selectable" class="w-0">
                            <input
                                type="checkbox"
                                class="checkbox"
                                :checked="allSelected"
                                :indeterminate="someSelected"
                                :aria-label="selectAllLabel"
                                @change="toggleAll(($event.target as HTMLInputElement).checked)"
                            />
                        </th>
                        <th
                            v-for="column in resolvedColumns"
                            :key="column.key"
                            :class="[
                                column.headerClass ?? column.class,
                                alignClass(column),
                                widthClass(column),
                                column.sortable ? 'cursor-pointer select-none' : '',
                            ]"
                            @click="sortBy(column)"
                        >
                            <span
                                class="flex items-center gap-1 whitespace-nowrap"
                                :class="{ 'justify-end': column.align === 'right' }"
                            >
                                {{ column.label }}
                                <TableHeaderFilter
                                    v-if="headerFilter(column)"
                                    :filter="headerFilter(column)!"
                                    :query-value="query.filters[column.filter!]"
                                    :column-label="column.label"
                                    @change="changeFilter"
                                />
                                <template v-if="column.sortable">
                                    <component
                                        :is="
                                            currentSortDirection(column) === 'asc'
                                                ? KG_ICONS.adminAction.sortAsc
                                                : currentSortDirection(column) === 'desc'
                                                  ? KG_ICONS.adminAction.sortDesc
                                                  : KG_ICONS.adminAction.sortNone
                                        "
                                        :class="{ 'text-muted': !currentSortDirection(column) }"
                                    />
                                </template>
                            </span>
                        </th>
                        <th v-if="showActionsColumn" class="sticky right-0 z-10 w-0" />
                    </tr>
                </thead>

                <tbody>
                    <template v-if="loading">
                        <tr v-for="i in skeletonCount" :key="`skeleton-${i}`">
                            <td v-if="selectable"><div class="skeleton size-4" /></td>
                            <td v-for="column in resolvedColumns" :key="column.key" :class="widthClass(column)">
                                <div class="skeleton h-4 w-full" />
                            </td>
                            <td v-if="showActionsColumn"><div class="skeleton h-6 w-16 ml-auto" /></td>
                        </tr>
                    </template>

                    <template v-else>
                        <template v-for="(row, index) in rows?.data ?? []" :key="rowIdentifier(row, index)">
                            <tr
                                class="group"
                                :class="rowLink.rowClass(resolveRowHref(row))"
                                @click="onRowClick(row, $event)"
                                @auxclick="onRowAuxClick(row, $event)"
                            >
                                <td v-if="selectable" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="checkbox"
                                        :checked="selected.includes(String(rowIdentifier(row, index)))"
                                        :aria-label="selectLabel?.(row)"
                                        @change="
                                            toggleRow(
                                                String(rowIdentifier(row, index)),
                                                ($event.target as HTMLInputElement).checked,
                                            )
                                        "
                                    />
                                </td>
                                <td
                                    v-for="column in resolvedColumns"
                                    :key="column.key"
                                    :class="[cellClass(row, column), alignClass(column), widthClass(column)]"
                                >
                                    <slot :name="`cell-${column.key}`" :row="row" :value="cellValue(row, column)">
                                        {{ cellValue(row, column) }}
                                    </slot>
                                </td>

                                <td v-if="showActionsColumn" class="sticky right-0 z-10">
                                    <TableRowActions
                                        :edit-href="canEdit && editUrl ? editUrl(row) : null"
                                        :delete-href="canDeleteThisRow(row) && deleteUrl ? deleteUrl(row) : null"
                                    >
                                        <slot name="buttons" :row="row" :index="index" />
                                    </TableRowActions>
                                </td>
                            </tr>

                            <tr v-if="$slots['row-details']">
                                <td :colspan="columnCount">
                                    <slot name="row-details" :row="row" :index="index" />
                                </td>
                            </tr>
                        </template>

                        <tr v-if="!rows?.data?.length">
                            <td :colspan="columnCount" class="py-8 text-center text-muted">
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
