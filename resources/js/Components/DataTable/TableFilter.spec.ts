import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }) }));

import TableFilter from './TableFilter.vue';
import type { FilterConfig } from '@/types/table';

function mountFilter(
    filter: FilterConfig,
    queryValue?: string,
    onChange?: (property: string, value: string | null) => void,
): HTMLElement {
    const host = document.createElement('div');
    const app = createApp(TableFilter, { filter, queryValue, onChange });
    app.config.globalProperties.$t = (key: string) => key;
    app.mount(host);
    return host.firstElementChild as HTMLElement;
}

describe('TableFilter', () => {
    it('keeps operator, value and remove on one non-wrapping row', () => {
        const row = mountFilter({ property: 'name', label: 'Name', type: 'text' });

        expect(row.classList.contains('flex-wrap')).toBe(false);
        expect(row.classList.contains('flex-nowrap')).toBe(true);

        const tags = Array.from(row.children).map((child) => child.tagName);
        expect(tags).toEqual(['SELECT', 'INPUT', 'BUTTON']);
    });

    it('keeps both between inputs on the same row', () => {
        const row = mountFilter(
            { property: 'issue_date', label: 'Date', type: 'date', defaultOperator: 'between' },
            'between:2026-01-01,2026-02-01',
        );

        expect(row.classList.contains('flex-wrap')).toBe(false);
        expect(Array.from(row.children).map((child) => child.tagName)).toEqual(['SELECT', 'DIV', 'DIV', 'BUTTON']);
    });

    it('renders date filters with the own date input, never a native one', () => {
        const row = mountFilter(
            { property: 'issue_date', label: 'Date', type: 'date', defaultOperator: 'between' },
            'between:2026-01-01,2026-02-01',
        );

        expect(row.querySelector('input[type="date"]')).toBeNull();
        const inputs = row.querySelectorAll<HTMLInputElement>('input[type="text"]');
        expect(Array.from(inputs).map((i) => i.value)).toEqual(['1. 1. 2026', '1. 2. 2026']);
        expect(row.querySelector('legend')!.classList.contains('sr-only')).toBe(true);
    });

    it('renders a single date operator as one date input and emits the formatted value', async () => {
        const changes: Array<[string, string | null]> = [];
        const row = mountFilter(
            { property: 'issue_date', label: 'Date', type: 'date', operators: ['=', '>='] },
            '>=:2026-01-01',
            (property, value) => changes.push([property, value]),
        );

        expect(row.querySelector('input[type="date"]')).toBeNull();
        const input = row.querySelector<HTMLInputElement>('input[type="text"]')!;
        input.value = '15.3.2026';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('blur'));
        await nextTick();

        expect(changes).toHaveLength(1);
        expect(changes[0]![0]).toBe('issue_date');
        expect(changes[0]![1]).toContain('2026-03-15');
    });
});
