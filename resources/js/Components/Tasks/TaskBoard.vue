<script setup lang="ts">
import { computed } from 'vue';
import { useTaskBoardDrag } from '@/Composables/useTaskBoardDrag';
import { applyPendingMoves, boardShowAllHref, type PendingMoves } from '@/utils/taskBoard';
import TaskBoardCard from './TaskBoardCard.vue';
import TaskBoardColumn from './TaskBoardColumn.vue';

const props = defineProps<{
    board: App.Data.Tasks.TaskBoardData;
    pending: PendingMoves;
    canCreate: boolean;
    filters: Record<string, string>;
}>();

const emit = defineEmits<{
    move: [task: App.Data.Tasks.TaskListItemData, status: App.Enums.TaskStatusEnum];
    create: [status: App.Enums.TaskStatusEnum];
}>();

const columns = computed(() => applyPendingMoves(props.board.columns, props.pending));

function findTask(taskId: string): App.Data.Tasks.TaskListItemData | undefined {
    return props.board.columns.flatMap((column) => column.tasks).find((task) => task.id === taskId);
}

const drag = useTaskBoardDrag((taskId, status) => {
    const task = findTask(taskId);
    if (task && task.can_update && task.status !== status && props.pending[taskId] === undefined) {
        emit('move', task, status);
    }
});
</script>

<template>
    <div
        scroll-region
        class="flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain scroll-px-7 -mx-7 px-7 pb-2 lg:snap-none"
    >
        <TaskBoardColumn
            v-for="column in columns"
            :key="column.status"
            :column="column"
            :can-create="canCreate"
            :drop-target="drag.overStatus.value === column.status"
            :show-all-href="column.has_more ? boardShowAllHref(column.status, filters) : null"
            @create="emit('create', column.status)"
            @dragenter="drag.dragEnter(column.status)"
            @dragleave="drag.dragLeave(column.status)"
            @drop="drag.drop(column.status)"
        >
            <TaskBoardCard
                v-for="task in column.tasks"
                :key="task.id"
                :task="task"
                :draggable="drag.finePointer.value && task.can_update && pending[task.id] === undefined"
                :busy="pending[task.id] !== undefined"
                @move="emit('move', task, $event)"
                @dragstart="drag.dragStart($event, task.id)"
                @dragend="drag.dragEnd()"
            />
        </TaskBoardColumn>
    </div>
</template>
