import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import StatusChip from './StatusChip.vue';

function render(props: Record<string, unknown>): HTMLElement {
    const host = document.createElement('div');
    createApp(StatusChip, props).mount(host);
    return host.firstElementChild as HTMLElement;
}

describe('StatusChip', () => {
    it('renders label and tone class', () => {
        const el = render({ tone: 'done', label: 'Zaplatená' });
        expect(el.getAttribute('role')).toBeNull();
        expect(el.classList.contains('kg-chip--done')).toBe(true);
        expect(el.textContent?.trim()).toBe('Zaplatená');
    });

    it('renders the default tone icon', () => {
        expect(render({ tone: 'danger', label: 'x' }).querySelector('svg')).not.toBeNull();
    });

    it('omits the icon for the neutral tone', () => {
        expect(render({ tone: 'neutral', label: 'x' }).querySelector('svg')).toBeNull();
    });
});
