<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    label: string;
    colors: readonly string[];
}>();

const emit = defineEmits<{
    pick: [color: string | null];
}>();

const { t } = useI18n();

const root = ref<HTMLDetailsElement | null>(null);

function onOutsideClick(event: MouseEvent): void {
    if (root.value?.open && !root.value.contains(event.target as Node)) root.value.open = false;
}

function onEscape(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || !root.value?.open) return;
    root.value.open = false;
    root.value.querySelector('summary')?.focus();
}

onMounted(() => document.addEventListener('mousedown', onOutsideClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onOutsideClick));

function pick(color: string | null): void {
    emit('pick', color);
    if (root.value) root.value.open = false;
}
</script>

<template>
    <details ref="root" class="dropdown" @keydown="onEscape">
        <summary class="btn btn-ghost btn-sm" :aria-label="label" :title="label">
            <slot />
        </summary>
        <div class="dropdown-content z-20 mt-1 w-48 p-2">
            <div class="grid grid-cols-6 gap-1">
                <button
                    v-for="color in colors"
                    :key="color"
                    type="button"
                    class="size-6 rounded-tile-sm"
                    :style="{ backgroundColor: color }"
                    :aria-label="t('editor_color_swatch', { color })"
                    :title="t('editor_color_swatch', { color })"
                    @click="pick(color)"
                />
            </div>
            <button type="button" class="btn btn-ghost btn-sm mt-2 w-full" @click="pick(null)">
                {{ t('editor_no_color') }}
            </button>
        </div>
    </details>
</template>
