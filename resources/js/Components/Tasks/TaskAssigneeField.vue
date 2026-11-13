<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import CloseIcon from '~icons/material-symbols/close-outline-rounded';

import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import { TASK_REFRESH_PROPS, flushActiveTaskEdit, taskEditIdle } from '@/Composables/useTaskInlineEdit';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    members: App.Data.Tasks.TaskMemberOptionData[];
    editable: boolean;
    disabled: boolean;
}>();

const { t } = useI18n();

const open = ref(false);
const menu = ref<HTMLElement | null>(null);

const form = useForm<{ assigned_membership_id: string | null }>('post', `/tasks/${props.task.id}/assign`, {
    assigned_membership_id: props.task.assigned_membership_id,
});

watch(
    () => props.task.assigned_membership_id,
    (value) => {
        form.assigned_membership_id = value;
        form.defaults({ assigned_membership_id: value });
    },
);

async function assign(id: string | null): Promise<void> {
    open.value = false;
    if (id === props.task.assigned_membership_id || form.processing) return;

    form.assigned_membership_id = id;
    if (!taskEditIdle()) await flushActiveTaskEdit();
    form.submit({
        preserveScroll: true,
        only: TASK_REFRESH_PROPS,
        onError: () => {
            form.assigned_membership_id = props.task.assigned_membership_id;
        },
    });
}

function onKeydown(event: KeyboardEvent): void {
    const list = Array.from(menu.value?.querySelectorAll<HTMLElement>('[role="menuitemradio"]') ?? []);
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
</script>

<template>
    <div v-if="editable" class="flex items-center gap-1">
        <AnchoredPopover v-model:open="open" class="min-w-56 p-1">
            <template #trigger="{ toggle, open: isOpen }">
                <button
                    type="button"
                    class="btn btn-ghost btn-sm kg-value-trigger -ms-3 justify-start"
                    :disabled="disabled"
                    aria-haspopup="menu"
                    :aria-expanded="isOpen"
                    :aria-label="`${t('task_assignee')}: ${task.assignee_name ?? t('task_unassigned')}`"
                    @click="toggle"
                >
                    <template v-if="task.assignee_name">
                        <InitialsAvatar :name="task.assignee_name" />
                        <span class="truncate">{{ task.assignee_name }}</span>
                    </template>
                    <span v-else class="kg-placeholder">{{ t('task_unassigned') }}</span>
                </button>
            </template>

            <div ref="menu" role="menu" :aria-label="t('task_assignee')" class="flex flex-col" @keydown="onKeydown">
                <button
                    v-for="member in members"
                    :key="member.id"
                    type="button"
                    role="menuitemradio"
                    class="flex items-center justify-between gap-3 min-h-(--kg-menu-item-height) rounded-tile-sm px-3 text-left hover:bg-base-100 focus-visible:bg-base-100"
                    :aria-checked="member.id === form.assigned_membership_id"
                    :data-autofocus="member.id === form.assigned_membership_id ? '' : undefined"
                    @click="assign(member.id)"
                >
                    <span class="flex items-center gap-2">
                        <InitialsAvatar :name="member.name" />
                        {{ member.name }}
                    </span>
                    <KG_ICONS.adminAction.check v-if="member.id === form.assigned_membership_id" aria-hidden="true" />
                </button>
            </div>
        </AnchoredPopover>

        <button
            v-if="task.assigned_membership_id"
            type="button"
            class="btn btn-ghost btn-square btn-sm"
            :disabled="disabled || form.processing"
            :aria-label="t('task_clear_assignee')"
            :title="t('task_clear_assignee')"
            @click="assign(null)"
        >
            <CloseIcon aria-hidden="true" />
        </button>
    </div>
    <p v-if="editable && form.errors.assigned_membership_id" class="text-sm text-error">
        {{ form.errors.assigned_membership_id }}
    </p>
    <span v-else-if="!editable">{{ task.assignee_name ?? t('task_unassigned') }}</span>
</template>
