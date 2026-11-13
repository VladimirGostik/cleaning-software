<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import { TASK_PANEL_PROPS, useTaskPanel } from '@/Composables/useTaskPanel';
import { usePageProps } from '@/Composables/usePageProps';
import { KG_ICONS } from '@/icons/kegosIcons';
import { isTaskRunListWorthy } from '@/utils/taskAi';
import { formatTaskDue, isTaskOverdue } from '@/utils/taskBoard';
import TaskAiRunBadge from './TaskAiRunBadge.vue';
import TaskPriorityBadge from './TaskPriorityBadge.vue';

const props = defineProps<{
    task: App.Data.Tasks.TaskListItemData;
    busy: boolean;
}>();

const emit = defineEmits<{
    toggle: [status: 'done' | 'open'];
}>();

const { t } = useI18n();
const { href: taskHref } = useTaskPanel();
const pageProps = usePageProps();

const overdue = computed(() => isTaskOverdue(props.task));

const due = computed(() => {
    if (!props.task.due_at) return null;

    const { kind, text } = formatTaskDue(props.task.due_at);
    const label = kind === 'today' ? t('task_due_today') : kind === 'tomorrow' ? t('task_due_tomorrow') : text;

    return { label, spoken: overdue.value ? `${label}, ${t('task_due_overdue_label')}` : label };
});

const firm = computed(() => {
    if (props.task.is_global) return t('task_personal');

    return pageProps.value.tenant.available.length >= 2 ? props.task.tenant_name : null;
});

const runWorthy = computed(() => props.task.ai_run_status !== null && isTaskRunListWorthy(props.task.ai_run_status));
</script>

<template>
    <div class="flex flex-col gap-[calc(var(--kg-row-gap)*2)]">
        <div class="flex items-start gap-1">
            <button
                v-if="task.can_update"
                type="button"
                class="btn btn-ghost btn-square btn-sm relative z-10 shrink-0"
                :class="task.is_active ? 'text-muted hover:text-primary' : 'text-primary'"
                :aria-label="t(task.is_active ? 'task_mark_done' : 'task_mark_open', { title: task.title })"
                :disabled="busy"
                @click="emit('toggle', task.is_active ? 'done' : 'open')"
            >
                <CheckCircleIcon aria-hidden="true" />
            </button>
            <span
                v-else
                class="btn btn-ghost btn-square btn-sm pointer-events-none shrink-0"
                :class="task.is_active ? 'text-muted' : 'text-primary'"
                aria-hidden="true"
            >
                <CheckCircleIcon />
            </span>
            <Link
                :href="taskHref(task.id)"
                :only="TASK_PANEL_PROPS"
                preserve-state
                preserve-scroll
                class="min-w-0 flex-1 break-words py-1 font-medium leading-6 line-clamp-3 after:absolute after:inset-0 after:content-['']"
                draggable="false"
            >
                {{ task.title }}
            </Link>
        </div>

        <div class="flex min-h-6 min-w-0 items-center gap-2 text-xs text-muted">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <InitialsAvatar v-if="task.assignee_name" :name="task.assignee_name" />
                <span v-if="firm" class="min-w-0 truncate" :title="firm">{{ firm }}</span>
                <span v-if="due" data-task-due class="shrink-0" :class="{ 'text-error': overdue }">
                    <span aria-hidden="true">{{ due.label }}</span>
                    <span class="sr-only">{{ due.spoken }}</span>
                </span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <TaskPriorityBadge v-if="task.priority === 'high'" :priority="task.priority" />
                <TaskAiRunBadge v-if="runWorthy && task.ai_run_status" :status="task.ai_run_status" />
                <span v-if="task.comments_count > 0" data-task-comments class="inline-flex items-center gap-1">
                    <KG_ICONS.task.comment aria-hidden="true" />
                    <span aria-hidden="true">{{ task.comments_count }}</span>
                    <span class="sr-only">{{ t('task_comments_count', task.comments_count) }}</span>
                </span>
                <span v-if="task.attachments_count > 0" data-task-attachments class="inline-flex items-center gap-1">
                    <KG_ICONS.task.attachment aria-hidden="true" />
                    <span aria-hidden="true">{{ task.attachments_count }}</span>
                    <span class="sr-only">{{ t('task_attachments_count', task.attachments_count) }}</span>
                </span>
            </div>
        </div>
    </div>
</template>
