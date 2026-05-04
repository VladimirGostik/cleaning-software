<script setup lang="ts">
    defineProps<{
        title: string;
        subtitle?: string;
    }>();

    defineSlots<{ badges?: () => unknown; actions?: () => unknown }>();
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold">{{ title }}</h1>
            <p v-if="subtitle" class="text-base-content/60 mt-1">{{ subtitle }}</p>
            <div v-if="$slots.badges" class="mt-2 flex gap-2">
                <slot name="badges" />
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
