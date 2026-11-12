<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import { formatDatetime } from '@/utils/date';
import { isTaskRunListWorthy } from '@/utils/taskAi';
import { isTaskOverdue } from '@/utils/taskBoard';
import TaskAiRunBadge from './TaskAiRunBadge.vue';
import TaskPriorityBadge from './TaskPriorityBadge.vue';
import TaskStatusMenu from './TaskStatusMenu.vue';

const props = defineProps<{
    task: App.Data.Tasks.TaskListItemData;
    draggable: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{
    move: [status: App.Enums.TaskStatusEnum];
    dragstart: [event: DragEvent];
    dragend: [];
}>();

const { t } = useI18n();

const overdue = computed(() => isTaskOverdue(props.task));
</script>

<template>
    <article
        class="kg-card-nested flex flex-col gap-2 p-3"
        :class="{ 'cursor-grab': draggable, 'opacity-60': busy }"
        :draggable="draggable"
        :aria-busy="busy"
        @dragstart="emit('dragstart', $event)"
        @dragend="emit('dragend')"
    >
        <div class="flex items-start justify-between gap-2">
            <Link :href="`/tasks/${task.id}`" class="link link-hover min-w-0 break-words font-medium" draggable="false">
                {{ task.title }}
            </Link>
            <TaskStatusMenu
                variant="icon"
                :status="task.status"
                :editable="task.can_update"
                :disabled="busy"
                @select="emit('move', $event)"
            />
        </div>

        <div
            v-if="
                task.priority === 'high' ||
                task.is_global ||
                (task.ai_run_status && isTaskRunListWorthy(task.ai_run_status))
            "
            class="flex flex-wrap items-center gap-1"
        >
            <TaskPriorityBadge v-if="task.priority === 'high'" :priority="task.priority" />
            <span v-if="task.is_global" class="kg-chip kg-chip--neutral kg-chip--sm">{{ t('task_personal') }}</span>
            <TaskAiRunBadge
                v-if="task.ai_run_status && isTaskRunListWorthy(task.ai_run_status)"
                :status="task.ai_run_status"
            />
        </div>

        <p v-if="task.due_at" class="text-sm" :class="overdue ? 'text-error' : 'text-muted'">
            <template v-if="overdue">{{ t('task_overdue') }}: </template>{{ formatDatetime(task.due_at) }}
        </p>

        <div class="flex items-center justify-between gap-2 text-sm text-muted">
            <InitialsAvatar v-if="task.assignee_name" :name="task.assignee_name" />
            <span v-else />
            <div class="flex items-center gap-3">
                <span
                    v-if="task.comments_count > 0"
                    class="inline-flex items-center gap-1"
                    :aria-label="t('task_comments_count', task.comments_count)"
                >
                    <KG_ICONS.task.comment aria-hidden="true" />
                    {{ task.comments_count }}
                </span>
                <span
                    v-if="task.attachments_count > 0"
                    class="inline-flex items-center gap-1"
                    :aria-label="t('task_attachments_count', task.attachments_count)"
                >
                    <KG_ICONS.task.attachment aria-hidden="true" />
                    {{ task.attachments_count }}
                </span>
            </div>
        </div>
    </article>
</template>
