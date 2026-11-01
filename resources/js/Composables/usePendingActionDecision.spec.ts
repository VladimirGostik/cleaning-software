import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { usePendingActionDecision } from './usePendingActionDecision';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn() } }));

const post = vi.mocked(router.post);

function action(kind: App.Enums.PendingActionEnum): App.Data.Ai.PendingActionData {
    return { id: 'a1', action: kind } as App.Data.Ai.PendingActionData;
}

type Options = Parameters<typeof router.post>[2];

function lastOptions(): NonNullable<Options> {
    return post.mock.calls.at(-1)![2]!;
}

beforeEach(() => post.mockReset());

describe('usePendingActionDecision', () => {
    it('posts a non-destructive confirm immediately', () => {
        const decision = usePendingActionDecision();

        decision.requestConfirm(action('issue_invoice'));

        expect(post).toHaveBeenCalledWith('/pending-actions/a1/confirm', undefined, expect.any(Object));
        expect(decision.toConfirm.value).toBeNull();
    });

    it('holds a destructive confirm behind the modal until confirmed', () => {
        const decision = usePendingActionDecision();

        decision.requestConfirm(action('delete_quote'));

        expect(post).not.toHaveBeenCalled();
        expect(decision.toConfirm.value?.id).toBe('a1');

        decision.confirmModal();

        expect(post).toHaveBeenCalledWith('/pending-actions/a1/confirm', undefined, expect.any(Object));
        expect(decision.toConfirm.value).toBeNull();
    });

    it('drops the pending confirm when the modal is dismissed', () => {
        const decision = usePendingActionDecision();
        decision.requestConfirm(action('cancel_invoice'));

        decision.dismissModal();
        decision.confirmModal();

        expect(post).not.toHaveBeenCalled();
    });

    it('posts cancel without a modal', () => {
        usePendingActionDecision().cancel(action('delete_task'));

        expect(post).toHaveBeenCalledWith('/pending-actions/a1/cancel', undefined, expect.any(Object));
    });

    it('tracks processing and surfaces errors.status', () => {
        const decision = usePendingActionDecision();
        decision.requestConfirm(action('send_quote'));
        const options = lastOptions();

        options.onStart?.({} as never);
        expect(decision.processingId.value).toBe('a1');

        options.onError?.({ status: 'Expired', other: 'x' });
        options.onFinish?.({} as never);

        expect(decision.errorMessage.value).toBe('Expired');
        expect(decision.processingId.value).toBeNull();
    });

    it('calls onSettled with the verb on success only', () => {
        const onSettled = vi.fn();
        const decision = usePendingActionDecision({ onSettled });
        const a = action('send_invoice');

        decision.cancel(a);
        lastOptions().onSuccess?.({} as never);

        expect(onSettled).toHaveBeenCalledWith(a, 'cancel');

        decision.requestConfirm(a);
        lastOptions().onError?.({ status: 'no' });

        expect(onSettled).toHaveBeenCalledTimes(1);
    });
});
