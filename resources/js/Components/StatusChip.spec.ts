import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import StatusChip from './StatusChip.vue';
import type { StatusTone } from './statusTone';

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

    it('renders no icon for any tone', () => {
        const tones: StatusTone[] = ['planned', 'in_progress', 'done', 'warning', 'danger', 'neutral'];
        for (const tone of tones) {
            expect(render({ tone, label: 'x' }).querySelector('svg')).toBeNull();
        }
    });

    it('marks the small size', () => {
        expect(render({ tone: 'neutral', label: 'x', size: 'sm' }).classList.contains('kg-chip--sm')).toBe(true);
    });
});
