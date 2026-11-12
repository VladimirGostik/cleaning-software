import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type Component } from 'vue';
import ListRow from './ListRow.vue';

vi.mock('@inertiajs/vue3', () => ({ Link: 'a' }));

function render(props: Record<string, unknown>, slots: Record<string, () => unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    createApp({ render: () => h(ListRow as Component, props, slots) }).mount(host);
    return host.firstElementChild as HTMLElement;
}

describe('ListRow', () => {
    it('renders a div by default with a flow-content trailing wrapper', () => {
        const el = render({ title: 'T' }, { trailing: () => h('div', 'x') });
        expect(el.tagName).toBe('DIV');
        expect(el.lastElementChild?.tagName).toBe('DIV');
    });

    it('renders a real button in button mode and forwards click', () => {
        const onClick = vi.fn();
        const el = render({ title: 'T', button: true, onClick }, { trailing: () => h('span', 'x') });
        expect(el.tagName).toBe('BUTTON');
        expect(el.getAttribute('type')).toBe('button');
        expect(el.lastElementChild?.tagName).toBe('SPAN');
        el.click();
        expect(onClick).toHaveBeenCalledOnce();
    });

    it('renders a link when href is set', () => {
        const el = render({ title: 'T', href: '/x' });
        expect(el.tagName).toBe('A');
        expect(el.getAttribute('href')).toBe('/x');
    });
});
