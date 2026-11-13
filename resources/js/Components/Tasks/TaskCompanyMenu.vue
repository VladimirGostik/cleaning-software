<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import LockIcon from '~icons/material-symbols/lock-outline-rounded';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import type { TaskCompanyOption } from '@/Composables/useTaskCreate';

const props = defineProps<{
    modelValue: string | null;
    options: TaskCompanyOption[];
    locked: boolean;
    lockedLabel: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | null];
    'update:open': [open: boolean];
}>();

const { t } = useI18n();
const open = ref(false);
const menu = ref<HTMLElement | null>(null);

const current = computed(() => props.options.find((option) => option.value === props.modelValue)?.label ?? '');

watch(open, (isOpen) => emit('update:open', isOpen));

function items(): HTMLElement[] {
    return Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitemradio"]') ?? []);
}

function onKeydown(event: KeyboardEvent): void {
    const list = items();
    const index = list.indexOf(document.activeElement as HTMLElement);
    const move: Record<string, number> = {
        ArrowDown: (index + 1) % list.length,
        ArrowUp: (index - 1 + list.length) % list.length,
        Home: 0,
        End: list.length - 1,
    };

    if (event.key in move) {
        event.preventDefault();
        list[move[event.key] as number]?.focus();
    }
}

function choose(value: string | null): void {
    open.value = false;
    if (value !== props.modelValue) emit('update:modelValue', value);
}
</script>

<template>
    <span v-if="locked" class="inline-flex items-center gap-1">
        <LockIcon class="text-muted" aria-hidden="true" />
        <span>{{ current }}</span>
        <span class="sr-only">{{ lockedLabel }}</span>
    </span>

    <span v-else-if="options.length < 2">{{ current }}</span>

    <AnchoredPopover v-else v-model:open="open" class="min-w-56 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="inline-flex items-center gap-0.5 rounded-full"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('task_change_company', { company: current })"
                @click="toggle"
            >
                <span>{{ current }}</span>
                <KG_ICONS.adminAction.caretDown class="text-muted" aria-hidden="true" />
            </button>
        </template>

        <div ref="menu" role="menu" :aria-label="t('task_company')" class="flex flex-col" @keydown="onKeydown">
            <button
                v-for="option in options"
                :key="option.value ?? 'global'"
                type="button"
                role="menuitemradio"
                class="flex items-center justify-between gap-3 min-h-(--kg-menu-item-height) rounded-tile-sm px-3 text-left hover:bg-base-100 focus-visible:bg-base-100"
                :aria-checked="option.value === modelValue"
                :data-autofocus="option.value === modelValue ? '' : undefined"
                @click="choose(option.value)"
            >
                <span>{{ option.label }}</span>
                <KG_ICONS.adminAction.check v-if="option.value === modelValue" aria-hidden="true" />
            </button>
        </div>
    </AnchoredPopover>
</template>
