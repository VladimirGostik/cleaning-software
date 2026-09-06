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
        class="flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-70"
    >
        <span
            v-if="processing"
            class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
