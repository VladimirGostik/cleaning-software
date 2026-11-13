<script setup lang="ts">
import TaskCard from './TaskCard.vue';

defineProps<{
    task: App.Data.Tasks.TaskListItemData;
    draggable: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{
    move: [status: App.Enums.TaskStatusEnum];
    dragstart: [event: DragEvent];
    dragend: [];
}>();
</script>

<template>
    <article
        class="kg-card-nested relative p-3"
        :class="{ 'cursor-grab': draggable, 'opacity-60': busy }"
        :draggable="draggable"
        :aria-busy="busy"
        @dragstart="emit('dragstart', $event)"
        @dragend="emit('dragend')"
    >
        <TaskCard :task="task" :busy="busy" @toggle="emit('move', $event)" />
    </article>
</template>
