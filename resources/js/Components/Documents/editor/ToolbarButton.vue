<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        pressed?: boolean;
        disabled?: boolean;
    }>(),
    {
        pressed: undefined,
        disabled: false,
    },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        class="btn btn-ghost btn-xs"
        :class="{ 'btn-active': pressed }"
        :aria-pressed="pressed"
        :aria-label="label"
        :title="label"
        :disabled="disabled"
        @click="$emit('click')"
    >
        <slot />
    </button>
</template>
