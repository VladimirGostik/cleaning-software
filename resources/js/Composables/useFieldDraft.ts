import { ref, watch } from 'vue';

export function useFieldDraft(source: () => string) {
    const draft = ref(source());
    const focused = ref(false);
    let synced = source();

    watch(source, (value) => {
        if (value === draft.value) {
            synced = value;
            return;
        }

        if (!focused.value && draft.value === synced) {
            draft.value = value;
            synced = value;
        }
    });

    function commit(): string | null {
        if (draft.value === synced) return null;

        synced = draft.value;

        return draft.value;
    }

    function revert(): void {
        draft.value = synced;
    }

    function isDirty(): boolean {
        return draft.value !== synced;
    }

    return { draft, focused, commit, revert, isDirty };
}
