<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import type { PaginatedMeta, PaginatedLinks } from '@/types/pagination';

    defineProps<{
        meta: PaginatedMeta;
        links: PaginatedLinks;
    }>();
</script>

<template>
    <div class="flex justify-between items-center mt-4 flex-wrap gap-2">
        <span class="text-sm text-base-content/60">
            {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} / {{ meta.total }}
        </span>
        <div class="join">
            <Link
                :href="links.prev ?? '#'"
                :class="['join-item btn btn-sm', !links.prev && 'btn-disabled pointer-events-none']"
                preserve-scroll
            >
                &laquo;
            </Link>
            <span class="join-item btn btn-sm btn-disabled pointer-events-none">
                {{ meta.current_page }} / {{ meta.last_page }}
            </span>
            <Link
                :href="links.next ?? '#'"
                :class="['join-item btn btn-sm', !links.next && 'btn-disabled pointer-events-none']"
                preserve-scroll
            >
                &raquo;
            </Link>
        </div>
    </div>
</template>
