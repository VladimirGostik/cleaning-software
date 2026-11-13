import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { flushActiveTaskEdit, taskEditIdle } from '@/Composables/useTaskInlineEdit';
import { useToast } from '@/Composables/useToast';

export function useTaskTransition() {
    const { t } = useI18n();
    const toast = useToast();
    const processingId = ref<string | null>(null);

    async function transition(taskId: string, action: 'complete' | 'reopen'): Promise<void> {
        if (!taskEditIdle()) await flushActiveTaskEdit();

        router.post(`/tasks/${taskId}/${action}`, undefined, {
            preserveScroll: true,
            onStart: () => (processingId.value = taskId),
            onFinish: () => (processingId.value = null),
            onError: (errors: Record<string, string>) =>
                toast.error(errors.status ?? Object.values(errors)[0] ?? t('forbidden')),
            onHttpException: (response: { status: number }) => {
                if (response.status === 403) {
                    toast.error(t('forbidden'));
                    return false;
                }
            },
        });
    }

    return { processingId, transition };
}
