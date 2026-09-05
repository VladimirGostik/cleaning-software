<script setup lang="ts">
defineProps<{
    cancelHref: string;
    cancelLabel?: string;
    submitLabel?: string;
    processing?: boolean;
}>();
</script>

<template>
    <div class="flex justify-end gap-2">
        <a
            :href="cancelHref"
            class="btn btn-ghost"
        >{{ cancelLabel ?? 'Cancel' }}</a>
        <slot name="extra" />
        <button
            type="submit"
            class="btn btn-primary"
            :disabled="processing"
        >
            <span
                v-if="processing"
                class="loading loading-spinner loading-xs"
            />
            {{ submitLabel ?? 'Save' }}
        </button>
    </div>
</template>
