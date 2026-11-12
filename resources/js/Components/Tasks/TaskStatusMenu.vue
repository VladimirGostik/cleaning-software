<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import { TASK_STATUSES, taskStatusKey } from '@/utils/enums';
import TaskStatusBadge from './TaskStatusBadge.vue';

const props = withDefaults(
    defineProps<{
        status: App.Enums.TaskStatusEnum;
        variant: 'chip' | 'icon';
        disabled?: boolean;
        editable?: boolean;
    }>(),
    { disabled: false, editable: true },
);

const emit = defineEmits<{
    select: [status: App.Enums.TaskStatusEnum];
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

function choose(status: App.Enums.TaskStatusEnum): void {
    open.value = false;
    if (status !== props.status) emit('select', status);
}
</script>

<template>
    <TaskStatusBadge v-if="!editable && variant === 'chip'" :status="status" />

    <AnchoredPopover v-else-if="editable" v-model:open="open" class="min-w-48 p-1">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                v-if="variant === 'chip'"
                type="button"
                class="inline-flex items-center gap-0.5 rounded-full disabled:opacity-60"
                :disabled="disabled"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('task_change_status', { status: t(taskStatusKey(status)) })"
                @click="toggle"
            >
                <TaskStatusBadge :status="status" />
                <KG_ICONS.adminAction.caretDown class="text-muted" aria-hidden="true" />
            </button>
            <button
                v-else
                type="button"
                class="btn btn-ghost btn-square"
                :disabled="disabled"
                aria-haspopup="menu"
                :aria-expanded="isOpen"
                :aria-label="t('task_move_to')"
                :title="t('task_move_to')"
                @click="toggle"
            >
                <KG_ICONS.adminAction.more />
            </button>
        </template>

        <div ref="menu" role="menu" :aria-label="t('task_move_to')" class="flex flex-col" @keydown="onKeydown">
            <button
                v-for="option in TASK_STATUSES"
                :key="option"
                type="button"
                role="menuitemradio"
                class="flex items-center justify-between gap-3 min-h-11 rounded-tile-sm px-3 text-left hover:bg-base-100 focus-visible:bg-base-100"
                :aria-checked="option === status"
                :data-autofocus="option === status ? '' : undefined"
                @click="choose(option)"
            >
                <TaskStatusBadge :status="option" />
                <KG_ICONS.adminAction.check v-if="option === status" aria-hidden="true" />
            </button>
        </div>
    </AnchoredPopover>
</template>
