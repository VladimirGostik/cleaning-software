<script setup lang="ts">
import { useTemplateRef } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{
    label: string;
    accept?: string;
    multiple?: boolean;
    capture?: 'environment';
    disabled?: boolean;
    iconOnly?: boolean;
}>();

const emit = defineEmits<{ selected: [files: File[]] }>();

const input = useTemplateRef<HTMLInputElement>('input');

function onChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []);

    target.value = '';

    if (files.length > 0) {
        emit('selected', files);
    }
}
</script>

<template>
    <button
        type="button"
        :title="iconOnly ? label : undefined"
        v-bind="$attrs"
        :disabled="disabled"
        :aria-label="iconOnly ? label : undefined"
        @click="input?.click()"
    >
        <slot />
        <template v-if="!iconOnly">{{ label }}</template>
    </button>
    <input
        ref="input"
        type="file"
        class="hidden"
        tabindex="-1"
        aria-hidden="true"
        :accept="accept"
        :multiple="multiple"
        :capture="capture"
        :disabled="disabled"
        @change="onChange"
    />
</template>
