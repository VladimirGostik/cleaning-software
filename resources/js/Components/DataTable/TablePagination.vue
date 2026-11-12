<script setup lang="ts">
import { computed } from 'vue';
import ChevronLeftIcon from '~icons/material-symbols/chevron-left-outline-rounded';
import ChevronRightIcon from '~icons/material-symbols/chevron-right-outline-rounded';
import FirstPageIcon from '~icons/material-symbols/first-page-outline-rounded';
import LastPageIcon from '~icons/material-symbols/last-page-outline-rounded';
import type { Paginator } from '@/types/table';

const props = withDefaults(
    defineProps<{
        rows: Paginator<unknown>;
        perPageOptions?: number[];
    }>(),
    {
        perPageOptions: () => [10, 25, 50, 100],
    },
);

const emit = defineEmits<{
    page: [page: number];
    perPage: [perPage: number];
}>();

const sizeOptions = computed(() => {
    const current = props.rows.per_page;
    if (!current || props.perPageOptions.includes(current)) return props.perPageOptions;
    return [...props.perPageOptions, current].sort((a, b) => a - b);
});

const visiblePages = computed(() => {
    const current = props.rows.current_page ?? 1;
    const last = props.rows.last_page ?? 1;
    const pages: number[] = [];
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    for (let page = start; page <= end; page++) pages.push(page);
    return pages;
});

function go(page: number) {
    const next = Math.min(Math.max(page, 1), props.rows.last_page || 1);
    emit('page', next);
}
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <div class="flex shrink-0 items-center gap-2">
                <span class="whitespace-nowrap text-muted">{{ $t('per_page') }}</span>
                <select
                    class="select w-24"
                    :value="rows.per_page"
                    data-autom="select-page-size"
                    @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in sizeOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>

            <span class="whitespace-nowrap text-muted">
                {{ rows.from ?? 0 }}–{{ rows.to ?? 0 }} / {{ rows.total ?? 0 }}
            </span>
        </div>

        <div v-if="rows.last_page > 1" class="join justify-center" role="navigation" :aria-label="$t('pagination_nav')">
            <button
                type="button"
                class="join-item btn btn-neutral btn-square"
                :aria-label="String(1)"
                :disabled="rows.current_page <= 1"
                @click="go(1)"
            >
                <FirstPageIcon aria-hidden="true" />
            </button>
            <button
                type="button"
                class="join-item btn btn-neutral btn-square"
                :aria-label="$t('previous')"
                :disabled="!rows.prev_page_url"
                @click="go(rows.current_page - 1)"
            >
                <ChevronLeftIcon aria-hidden="true" />
            </button>
            <button
                v-for="page in visiblePages"
                :key="page"
                type="button"
                class="join-item btn btn-square"
                :class="page === rows.current_page ? 'btn-primary' : 'btn-neutral'"
                :aria-current="page === rows.current_page ? 'page' : undefined"
                @click="go(page)"
            >
                {{ page }}
            </button>
            <button
                type="button"
                class="join-item btn btn-neutral btn-square"
                :aria-label="$t('next')"
                :disabled="!rows.next_page_url"
                @click="go(rows.current_page + 1)"
            >
                <ChevronRightIcon aria-hidden="true" />
            </button>
            <button
                type="button"
                class="join-item btn btn-neutral btn-square"
                :aria-label="String(rows.last_page)"
                :disabled="rows.current_page >= rows.last_page"
                @click="go(rows.last_page)"
            >
                <LastPageIcon aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
