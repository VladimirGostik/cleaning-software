import { describe, expect, it } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import { useFieldDraft } from './useFieldDraft';

function setup(initial = 'A') {
    const source = ref(initial);
    const draft = effectScope().run(() => useFieldDraft(() => source.value))!;

    return { source, draft };
}

describe('useFieldDraft', () => {
    it('follows the source while not focused and not dirty', async () => {
        const { source, draft } = setup();

        source.value = 'B';
        await nextTick();

        expect(draft.draft.value).toBe('B');
    });

    it('keeps an unsaved draft when the source changes', async () => {
        const { source, draft } = setup();

        draft.draft.value = 'typed';
        source.value = 'B';
        await nextTick();

        expect(draft.draft.value).toBe('typed');
        expect(draft.isDirty()).toBe(true);
    });

    it('keeps the draft while focused even when clean', async () => {
        const { source, draft } = setup();

        draft.focused.value = true;
        source.value = 'B';
        await nextTick();

        expect(draft.draft.value).toBe('A');
    });

    it('commit returns the changed value once and null when unchanged', () => {
        const { draft } = setup();

        expect(draft.commit()).toBeNull();

        draft.draft.value = 'X';

        expect(draft.commit()).toBe('X');
        expect(draft.commit()).toBeNull();
    });

    it('treats a source that catches up with the draft as saved', async () => {
        const { source, draft } = setup();

        draft.draft.value = 'X';
        source.value = 'X';
        await nextTick();

        expect(draft.isDirty()).toBe(false);
        expect(draft.commit()).toBeNull();
    });

    it('revert goes back to the last synced value', () => {
        const { draft } = setup();

        draft.draft.value = 'X';
        draft.revert();

        expect(draft.draft.value).toBe('A');
    });
});
