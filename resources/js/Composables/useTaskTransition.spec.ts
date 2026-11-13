import { beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope } from 'vue';
import { router } from '@inertiajs/vue3';

import { useTaskInlineEdit } from './useTaskInlineEdit';
import { useTaskTransition } from './useTaskTransition';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ error: vi.fn() }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { patch: vi.fn(), post: vi.fn() } }));

type PatchOptions = { onStart: () => void; onSuccess: () => void; onFinish: () => void };

const patch = vi.mocked(router.patch);
const post = vi.mocked(router.post);

describe('useTaskTransition after an inline edit', () => {
    beforeEach(() => {
        patch.mockReset();
        post.mockReset();
        patch.mockImplementation(((_url: string, _data: unknown, options: PatchOptions) => {
            options.onStart();
        }) as never);
    });

    it('sends the PATCH first and the complete only after it finished, state saved', async () => {
        const scope = effectScope();
        const edit = scope.run(() => useTaskInlineEdit({ taskId: 't1' }))!;
        const { transition } = useTaskTransition();

        edit.save({ title: 'Nový názov' });
        expect(patch).toHaveBeenCalledTimes(1);

        const pending = transition('t1', 'complete');
        await Promise.resolve();
        expect(post).not.toHaveBeenCalled();

        const options = patch.mock.calls[0]![2] as unknown as PatchOptions;
        options.onSuccess();
        options.onFinish();
        await pending;

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0]![0]).toBe('/tasks/t1/complete');
        expect(edit.state.value).toBe('saved');
        scope.stop();
    });

    it('posts at once when no edit is active', () => {
        void useTaskTransition().transition('t2', 'reopen');

        expect(post).toHaveBeenCalledTimes(1);
    });
});
