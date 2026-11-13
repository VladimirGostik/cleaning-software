<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import { TASK_PRIORITIES, taskPriorityKey } from '@/utils/enums';
import TaskPriorityBadge from './TaskPriorityBadge.vue';

const props = withDefaults(
    defineProps<{
        priority: App.Enums.TaskPriorityEnum;
        editable?: boolean;
        disabled?: boolean;
    }>(),
    { editable: true, disabled: false },
);

const emit = defineEmits<{
    select: [priority: App.Enums.TaskPriorityEnum];
}>();

const { t } = useI18n();
const open = ref(false);
const menu = ref<HTMLElement | null>(null);

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

function choose(priority: App.Enums.TaskPriorityEnum): void {
    open.value = false;
    if (priority !== props.priority) emit('select', priority);
}
</script>

<template>
    <TaskPriorityBadge v-if="!editable" :priority="priority" />

    <AnchoredPopover v-else v-model:open="open" class="min-w-48 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="inline-flex items-center gap-0.5 rounded-full disabled:opacity-60"
                :disabled="disabled"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('task_change_priority', { priority: t(taskPriorityKey(priority)) })"
                @click="toggle"
            >
                <TaskPriorityBadge :priority="priority" />
                <KG_ICONS.adminAction.caretDown class="text-muted" aria-hidden="true" />
            </button>
        </template>

        <div ref="menu" role="menu" :aria-label="t('task_priority')" class="flex flex-col" @keydown="onKeydown">
            <button
                v-for="option in TASK_PRIORITIES"
                :key="option"
                type="button"
                role="menuitemradio"
                class="flex items-center justify-between gap-3 min-h-(--kg-menu-item-height) rounded-tile-sm px-3 text-left hover:bg-base-100 focus-visible:bg-base-100"
                :aria-checked="option === priority"
                :data-autofocus="option === priority ? '' : undefined"
                @click="choose(option)"
            >
                <TaskPriorityBadge :priority="option" />
                <KG_ICONS.adminAction.check v-if="option === priority" aria-hidden="true" />
            </button>
        </div>
    </AnchoredPopover>
</template>
