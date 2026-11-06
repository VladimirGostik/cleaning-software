import { onBeforeUnmount, onMounted, ref } from 'vue';

const FINE_POINTER_QUERY = '(pointer: fine)';

export function useTaskBoardDrag(onDropTask: (taskId: string, status: App.Enums.TaskStatusEnum) => void) {
    const finePointer = ref(false);
    const draggingId = ref<string | null>(null);
    const overStatus = ref<App.Enums.TaskStatusEnum | null>(null);

    const depth: Partial<Record<App.Enums.TaskStatusEnum, number>> = {};
    let media: ReturnType<typeof window.matchMedia> | null = null;
    const syncPointer = () => {
        finePointer.value = media?.matches ?? false;
    };

    onMounted(() => {
        media = window.matchMedia(FINE_POINTER_QUERY);
        syncPointer();
        media.addEventListener('change', syncPointer);
    });

    onBeforeUnmount(() => media?.removeEventListener('change', syncPointer));

    function dragStart(event: DragEvent, taskId: string): void {
        draggingId.value = taskId;
        if (!event.dataTransfer) return;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', taskId);
    }

    function dragEnd(): void {
        Object.keys(depth).forEach((key) => delete depth[key as App.Enums.TaskStatusEnum]);
        draggingId.value = null;
        overStatus.value = null;
    }

    function dragEnter(status: App.Enums.TaskStatusEnum): void {
        if (!draggingId.value) return;
        depth[status] = (depth[status] ?? 0) + 1;
        overStatus.value = status;
    }

    function dragLeave(status: App.Enums.TaskStatusEnum): void {
        depth[status] = Math.max((depth[status] ?? 0) - 1, 0);
        if (depth[status] === 0 && overStatus.value === status) overStatus.value = null;
    }

    function drop(status: App.Enums.TaskStatusEnum): void {
        const taskId = draggingId.value;
        dragEnd();
        if (taskId) onDropTask(taskId, status);
    }

    return { finePointer, draggingId, overStatus, dragStart, dragEnd, dragEnter, dragLeave, drop };
}
