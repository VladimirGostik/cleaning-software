import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { flushActiveTaskEdit, taskEditIdle } from '@/Composables/useTaskInlineEdit';
import { useToast } from '@/Composables/useToast';
import { taskStatusKey } from '@/utils/enums';
import type { PendingMoves } from '@/utils/taskBoard';

interface ChangeTarget {
    id: string;
    title: string;
    status: App.Enums.TaskStatusEnum;
}

export function useTaskStatusChange() {
    const { t } = useI18n();
    const toast = useToast();
    const pending = ref<PendingMoves>({});
    const announcement = ref('');

    function settle(taskId: string): void {
        const rest = { ...pending.value };
        delete rest[taskId];
        pending.value = rest;
    }

    async function change(task: ChangeTarget, status: App.Enums.TaskStatusEnum): Promise<void> {
        if (task.status === status || pending.value[task.id] !== undefined) return;

        pending.value = { ...pending.value, [task.id]: status };

        if (!taskEditIdle()) await flushActiveTaskEdit();

        router.post(
            `/tasks/${task.id}/status`,
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    announcement.value = t('task_moved_announce', {
                        title: task.title,
                        status: t(taskStatusKey(status)),
                    });
                },
                onError: (errors: Record<string, string>) =>
                    toast.error(errors.status ?? Object.values(errors)[0] ?? t('forbidden')),
                onHttpException: (response: { status: number }) => {
                    if (response.status === 403) {
                        toast.error(t('forbidden'));
                        return false;
                    }
                },
                onFinish: () => settle(task.id),
            },
        );
    }

    return { pending, announcement, change };
}
