import { router } from '@inertiajs/vue3';
import { ref, type InjectionKey } from 'vue';
import { useI18n } from 'vue-i18n';

export type PendingActionVerb = 'confirm' | 'cancel';

export const DESTRUCTIVE_ACTIONS: readonly App.Enums.PendingActionEnum[] = [
    'cancel_invoice',
    'delete_invoice_draft',
    'delete_quote',
    'delete_task',
    'delete_expense',
];

export type PendingActionDecision = ReturnType<typeof usePendingActionDecision>;

export const PENDING_ACTION_DECISION: InjectionKey<PendingActionDecision> = Symbol('pending-action-decision');

export function usePendingActionDecision(
    options: {
        onSettled?: (action: App.Data.Ai.PendingActionData, verb: PendingActionVerb) => void;
    } = {},
) {
    const { t } = useI18n();

    const processingId = ref<string | null>(null);
    const errorMessage = ref<string | null>(null);
    const toConfirm = ref<App.Data.Ai.PendingActionData | null>(null);

    function post(action: App.Data.Ai.PendingActionData, verb: PendingActionVerb): void {
        router.post(`/pending-actions/${action.id}/${verb}`, undefined, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                processingId.value = action.id;
                errorMessage.value = null;
            },
            onSuccess: () => options.onSettled?.(action, verb),
            onFinish: () => (processingId.value = null),
            onError: (errors: Record<string, string>) => {
                errorMessage.value = errors.status ?? Object.values(errors)[0] ?? t('expense_request_failed');
            },
        });
    }

    function requestConfirm(action: App.Data.Ai.PendingActionData): void {
        if (DESTRUCTIVE_ACTIONS.includes(action.action)) {
            toConfirm.value = action;
            return;
        }
        post(action, 'confirm');
    }

    function cancel(action: App.Data.Ai.PendingActionData): void {
        post(action, 'cancel');
    }

    function confirmModal(): void {
        const action = toConfirm.value;
        toConfirm.value = null;
        if (action) post(action, 'confirm');
    }

    function dismissModal(): void {
        toConfirm.value = null;
    }

    return { processingId, errorMessage, toConfirm, requestConfirm, cancel, confirmModal, dismissModal };
}
