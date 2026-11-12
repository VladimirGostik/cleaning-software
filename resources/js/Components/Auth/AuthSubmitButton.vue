<script setup lang="ts">
defineProps<{
    processing: boolean;
}>();
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        :aria-busy="processing"
        class="flex w-full items-center justify-center gap-2 text-sm font-medium transition auth-submit-btn disabled:opacity-70"
    >
        <span v-if="processing" class="loading loading-spinner loading-xs" aria-hidden="true" />
        <slot />
    </button>
</template>
