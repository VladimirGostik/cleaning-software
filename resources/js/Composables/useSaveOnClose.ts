import { onBeforeUnmount, watch, type Ref } from 'vue';

export function useSaveOnClose(open: Ref<boolean>, onClose: (discarded: boolean) => void): void {
    let escaped = false;

    function mark(event: KeyboardEvent): void {
        if (event.key === 'Escape') escaped = true;
    }

    function stop(): void {
        window.removeEventListener('keydown', mark, true);
    }

    watch(open, (isOpen) => {
        if (isOpen) {
            escaped = false;
            window.addEventListener('keydown', mark, true);
            return;
        }

        stop();
        const discarded = escaped;
        escaped = false;
        onClose(discarded);
    });

    onBeforeUnmount(stop);
}
