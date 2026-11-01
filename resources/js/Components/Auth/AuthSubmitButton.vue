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
        class="flex w-full items-center justify-center rounded-field py-2.5 min-h-11 text-sm font-semibold transition auth-submit-btn disabled:opacity-70"
    >
        <span
            v-if="processing"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-base-content/20 border-t-base-content"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
