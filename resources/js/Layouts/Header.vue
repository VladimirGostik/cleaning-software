<script setup lang="ts">
import type { Breadcrumb } from '@/types';

defineProps<{
    title: string;
    breadcrumbs?: Breadcrumb[];
}>();
</script>

<template>
    <div class="flex flex-col gap-2 mb-6">
        <!-- Breadcrumbs -->
        <div
            v-if="breadcrumbs && breadcrumbs.length > 0"
            class="breadcrumbs text-sm"
        >
            <ul>
                <li
                    v-for="(crumb, index) in breadcrumbs"
                    :key="index"
                >
                    <a
                        v-if="crumb.url"
                        :href="crumb.url"
                    >
                        {{ crumb.label }}
                    </a>
                    <span v-else>{{ crumb.label }}</span>
                </li>
            </ul>
        </div>

        <!-- Title + actions row -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
