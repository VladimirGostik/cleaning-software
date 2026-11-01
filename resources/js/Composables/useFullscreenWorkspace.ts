import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

export function useFullscreenWorkspace(onEscape?: () => boolean): { fullscreen: Ref<boolean> } {
    const fullscreen = ref(false);

    function onKeydown(event: KeyboardEvent): void {
        if (event.key !== 'Escape') return;
        if (onEscape?.()) return;
        fullscreen.value = false;
    }

    watch(fullscreen, (on) => {
        document.documentElement.classList.toggle('overflow-hidden', on);
    });

    onMounted(() => window.addEventListener('keydown', onKeydown));

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeydown);
        document.documentElement.classList.remove('overflow-hidden');
    });

    return { fullscreen };
}
