import { describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, nextTick, ref } from 'vue';

import { useAutosize } from './useAutosize';

function setup() {
    const text = ref('a');
    const host = document.createElement('div');
    document.body.appendChild(host);
    createApp(
        defineComponent({
            setup() {
                const el = ref<HTMLTextAreaElement | null>(null);
                useAutosize(el, () => text.value);

                return () => h('textarea', { ref: el });
            },
        }),
    ).mount(host);
    const textarea = host.querySelector('textarea')!;

    Object.defineProperty(textarea, 'scrollHeight', { configurable: true, value: 90 });
    Object.defineProperty(textarea, 'offsetHeight', { configurable: true, value: 44 });
    Object.defineProperty(textarea, 'clientHeight', { configurable: true, value: 40 });

    return { text, textarea };
}

describe('useAutosize', () => {
    it('sets the height from scrollHeight plus the border when the text changes', async () => {
        const { text, textarea } = setup();

        text.value = 'a much longer text';
        await nextTick();
        await nextTick();

        expect(textarea.style.height).toBe('94px');
    });
});
