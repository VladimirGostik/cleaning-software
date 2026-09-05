import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

export interface UseDeleteConfirmOptions<T> {
    resolveUrl: (item: T) => string;
    getTitle: (item: T) => string;
    getDescription: (item: T) => string;
    method?: 'delete' | 'post';
}

// Use a wrapper object so reactive() does not try to deeply unwrap T
interface DeleteConfirmStateHolder<T> {
    isOpen: boolean;
    item: T | null;
}

export function useDeleteConfirm<T>(options: UseDeleteConfirmOptions<T>) {
    const { resolveUrl, getTitle, getDescription, method = 'delete' } = options;

    const state = reactive<DeleteConfirmStateHolder<T>>({
        isOpen: false,
        item: null,
    });

    function openModal(item: T) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        state.item = item as any;
        state.isOpen = true;
    }

    function closeModal() {
        state.isOpen = false;
        state.item = null;
    }

    function confirmDelete() {
        if (!state.item) return;

        const url = resolveUrl(state.item as T);

        if (method === 'delete') {
            router.delete(url, {
                preserveScroll: true,
                onSuccess: () => closeModal(),
                onError: () => closeModal(),
            });
        } else {
            router.post(url, undefined, {
                preserveScroll: true,
                onSuccess: () => closeModal(),
                onError: () => closeModal(),
            });
        }
    }

    function getModalTitle(): string {
        if (!state.item) return '';
        return getTitle(state.item as T);
    }

    function getModalDescription(): string {
        if (!state.item) return '';
        return getDescription(state.item as T);
    }

    return {
        state,
        openModal,
        closeModal,
        confirmDelete,
        getModalTitle,
        getModalDescription,
    };
}
