<script setup lang="ts">
import {computed} from 'vue';
import type {Paginator} from '@/types/table';


const props = withDefaults(defineProps<{
    rows: Paginator<unknown>;
    perPageOptions?: number[];
}>(), {
    perPageOptions: () => [10, 25, 50, 100],
});

const emit = defineEmits<{
    page: [page: number];
    perPage: [perPage: number];
}>();

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
                <span class="text-base-content/60 whitespace-nowrap">{{ $t('per_page') }}</span>
                <select
                    class="select select-sm select-bordered"
                    :value="rows.per_page"
                    data-autom="select-page-size"
                    @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </div>

            <span class="text-base-content/60 whitespace-nowrap">
        {{ rows.from ?? 0 }}–{{ rows.to ?? 0 }} / {{ rows.total ?? 0 }}
      </span>
        </div>

        <div v-if="rows.last_page > 1" class="join justify-center">
            <button type="button" class="join-item btn btn-sm" :disabled="rows.current_page <= 1" @click="go(1)">«
            </button>
            <button
                type="button"
                class="join-item btn btn-sm"
                :disabled="!rows.prev_page_url"
                @click="go(rows.current_page - 1)"
            >‹</button>
            <button
                v-for="page in visiblePages"
                :key="page"
                type="button"
                class="join-item btn btn-sm"
                :class="{ 'btn-active': page === rows.current_page }"
                @click="go(page)"
            >
                {{ page }}
            </button>
            <button
                type="button"
                class="join-item btn btn-sm"
                :disabled="!rows.next_page_url"
                @click="go(rows.current_page + 1)"
            >›</button>
            <button
                type="button"
                class="join-item btn btn-sm"
                :disabled="rows.current_page >= rows.last_page"
                @click="go(rows.last_page)"
            >»</button>
        </div>
    </div>
</template>
