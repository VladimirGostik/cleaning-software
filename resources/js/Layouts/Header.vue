<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Breadcrumb } from '@/types';

defineProps<{
    title: string;
    breadcrumbs?: Breadcrumb[];
}>();
</script>

<template>
    <Teleport defer to="#page-header">
        <div class="flex flex-col gap-1">
            <div v-if="breadcrumbs && breadcrumbs.length > 0" class="breadcrumbs py-0 text-xs text-muted">
                <ul>
                    <li v-for="(crumb, index) in breadcrumbs" :key="index">
                        <Link v-if="crumb.url" :href="crumb.url">
                            {{ crumb.label }}
                        </Link>
                        <span v-else>{{ crumb.label }}</span>
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="min-w-0 break-words text-2xl font-normal leading-tight">
                    {{ title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
