<script setup lang="ts">
withDefaults(
    defineProps<{
        processing: boolean;
        submitLabel: string;
        cancelLabel: string;
        cancelHref?: string;
    }>(),
    {
        cancelHref: undefined,
    },
);

const emit = defineEmits<{
    cancel: [];
}>();
</script>

<template>
    <div class="flex justify-end gap-2">
        <a v-if="cancelHref" :href="cancelHref" class="btn btn-ghost">
            {{ cancelLabel }}
        </a>
        <button v-else type="button" class="btn btn-ghost" @click="emit('cancel')">
            {{ cancelLabel }}
        </button>
        <button type="submit" class="btn btn-primary" :disabled="processing">
            <span v-if="processing" class="loading loading-spinner loading-xs" />
            {{ submitLabel }}
        </button>
    </div>
</template>
