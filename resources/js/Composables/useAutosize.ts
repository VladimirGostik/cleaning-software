import { nextTick, onMounted, watch } from 'vue';
import type { Ref } from 'vue';

export function useAutosize(el: Ref<HTMLTextAreaElement | null>, source: () => string): { resize: () => void } {
    // field-sizing: content is not supported everywhere (Safari, Firefox), so the textarea grows here.
    function resize(): void {
        const target = el.value;
        if (!target) return;

        target.style.height = 'auto';
        target.style.height = `${target.scrollHeight + target.offsetHeight - target.clientHeight}px`;
    }

    watch(source, () => nextTick(resize));
    onMounted(resize);

    return { resize };
}
