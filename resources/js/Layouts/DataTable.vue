<script setup lang="ts" generic="TRow extends object">
import { computed, ref, useSlots } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ChevronUpIcon,
    ChevronDownIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/solid';
import type { TableColumn, Paginator } from '@/types';


const props = withDefaults(
    defineProps<{
        columns?: TableColumn[];
        rows: Paginator<TRow>;
        perPage?: number;
        sort?: string;
        loading?: boolean;
        editUrl?: (row: TRow) => string;
        deleteUrl?: (row: TRow) => string;
        canEdit?: boolean;
        canDelete?: boolean;
        canDeleteRow?: (row: TRow) => boolean;
    }>(),
    {
        columns: undefined,
        editUrl: undefined,
        deleteUrl: undefined,
        canDeleteRow: undefined,
        perPage: 25,
        sort: '',
        loading: false,
        canEdit: false,
        canDelete: false,
    },
);

const emit = defineEmits<{
    'update:perPage': [value: number];
    'update:sort': [value: string];
}>();

const slots = useSlots();

const resolvedColumns = computed<TableColumn[]>(() => {
    if (props.columns) return props.columns;
    const first = props.rows?.data[0];
    if (!first) return [];
    return Object.keys(first).map((key) => ({
        key,
        label: key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
        sortable: true,
    }));
});

const showActionsColumn = computed(
    () =>
        (props.canEdit && !!props.editUrl) ||
        (props.canDelete && !!props.deleteUrl) ||
        !!slots['buttons'],
);

const popoverOpen = ref<Record<number, boolean>>({});

function openDeletePopover(index: number) {
    popoverOpen.value = { [index]: true };
}

function closeDeletePopover(index: number) {
    popoverOpen.value[index] = false;
}

function confirmDelete(row: TRow, index: number) {
    if (!props.deleteUrl) return;
    closeDeletePopover(index);
    router.delete(props.deleteUrl(row), { preserveScroll: true });
}

function canDeleteThisRow(row: TRow): boolean {
    return props.canDelete && !!props.deleteUrl && (props.canDeleteRow ? props.canDeleteRow(row) : true);
}

function handleSort(column: TableColumn) {
    if (!column.sortable) return;
    const currentSort = props.sort ?? '';
    let newSort: string;
    if (currentSort === column.key) {
        newSort = `-${column.key}`;
    } else if (currentSort === `-${column.key}`) {
        newSort = '';
    } else {
        newSort = column.key;
    }
    emit('update:sort', newSort);
}

function getSortDirection(column: TableColumn): 'asc' | 'desc' | null {
    if (!column.sortable) return null;
    const currentSort = props.sort ?? '';
    if (currentSort === column.key) return 'asc';
    if (currentSort === `-${column.key}`) return 'desc';
    return null;
}

const totalPages = computed(() => props.rows?.last_page ?? 1);

function getPageUrl(page: number): string | null {
    if (page < 1 || page > totalPages.value) return null;
    const link = props.rows?.links.find((l) => l.label === String(page));
    return link?.url ?? null;
}

const visiblePages = computed(() => {
    const current = props.rows?.current_page ?? 1;
    const last = props.rows?.last_page ?? 1;
    const pages: number[] = [];
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const perPageOptions = [10, 25, 50, 100];
const skeletonCount = computed(() => Math.min(props.perPage, 5));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <thead>
                    <tr>
                        <th
                            v-for="column in resolvedColumns"
                            :key="column.key"
                            :class="[column.class, column.sortable ? 'cursor-pointer select-none' : '']"
                            @click="handleSort(column)"
                        >
                            <span class="flex items-center gap-1">
                                {{ column.label }}
                                <template v-if="column.sortable">
                                    <ChevronUpIcon
                                        v-if="getSortDirection(column) === 'asc'"
                                        class="size-3"
                                    />
                                    <ChevronDownIcon
                                        v-else-if="getSortDirection(column) === 'desc'"
                                        class="size-3"
                                    />
                                    <ChevronUpIcon v-else class="size-3 opacity-30" />
                                </template>
                            </span>
                        </th>
                        <th v-if="showActionsColumn" class="w-0" />
                    </tr>
                </thead>
                <tbody>
                    <template v-if="loading">
                        <tr v-for="i in skeletonCount" :key="`skel-${i}`">
                            <td v-for="column in resolvedColumns" :key="column.key">
                                <div class="skeleton h-4 w-full" />
                            </td>
                            <td v-if="showActionsColumn">
                                <div class="skeleton h-6 w-16 ml-auto" />
                            </td>
                        </tr>
                    </template>

                    <template v-else>
                        <tr v-for="(row, index) in rows?.data ?? []" :key="index" class="group">
                            <td
                                v-for="column in resolvedColumns"
                                :key="column.key"
                                :class="column.class"
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :row="row"
                                    :value="(row as Record<string, unknown>)[column.key]"
                                >
                                    {{ (row as Record<string, unknown>)[column.key] }}
                                </slot>
                            </td>

                            <td v-if="showActionsColumn">
                                <div class="flex gap-1 justify-end items-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <slot name="buttons" :row="row" :index="index" />

                                    <a
                                        v-if="canEdit && editUrl"
                                        :href="editUrl(row)"
                                        class="btn btn-ghost btn-xs"
                                        :title="$t('edit')"
                                    >
                                        <PencilSquareIcon class="size-4" />
                                    </a>

                                    <div v-if="canDeleteThisRow(row)" class="relative">
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs text-error"
                                            :title="$t('delete')"
                                            @click="openDeletePopover(index)"
                                        >
                                            <TrashIcon class="size-4" />
                                        </button>

                                        <div
                                            v-if="popoverOpen[index]"
                                            class="absolute right-0 bottom-full mb-1 z-50 w-44 rounded-box bg-base-100 shadow-lg border border-base-300 p-3 flex flex-col gap-2"
                                        >
                                            <p class="text-sm text-center font-medium">{{ $t('confirm_delete') }}</p>
                                            <div class="flex gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-ghost btn-sm flex-1"
                                                    @click="closeDeletePopover(index)"
                                                >
                                                    {{ $t('no') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-error btn-sm flex-1"
                                                    @click="confirmDelete(row, index)"
                                                >
                                                    {{ $t('yes') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!rows?.data?.length">
                            <td
                                :colspan="resolvedColumns.length + (showActionsColumn ? 1 : 0)"
                                class="text-center text-base-content/50 py-8"
                            >
                                {{ $t('no_results') }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm">{{ $t('per_page') }}:</span>
                <select
                    class="select select-sm select-bordered"
                    :value="perPage"
                    @change="emit('update:perPage', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="opt in perPageOptions" :key="opt" :value="opt">
                        {{ opt }}
                    </option>
                </select>
            </div>
            <span class="text-sm text-base-content/60">
                {{ rows?.from ?? 0 }}&ndash;{{ rows?.to ?? 0 }} of {{ rows?.total ?? 0 }}
            </span>
        </div>

        <div v-if="(rows?.last_page ?? 0) > 1" class="flex justify-center">
            <div class="join">
                <a
                    v-if="rows?.prev_page_url"
                    :href="rows.prev_page_url"
                    class="join-item btn btn-sm"
                >
                    &laquo;
                </a>
                <template v-for="page in visiblePages" :key="page">
                    <a
                        :href="getPageUrl(page) ?? '#'"
                        class="join-item btn btn-sm"
                        :class="{ 'btn-active': page === rows?.current_page }"
                    >
                        {{ page }}
                    </a>
                </template>
                <a
                    v-if="rows?.next_page_url"
                    :href="rows.next_page_url"
                    class="join-item btn btn-sm"
                >
                    &raquo;
                </a>
            </div>
        </div>
    </div>
</template>
