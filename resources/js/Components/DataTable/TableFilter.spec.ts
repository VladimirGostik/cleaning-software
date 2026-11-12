import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import TableFilter from './TableFilter.vue';
import type { FilterConfig } from '@/types/table';

function mountFilter(filter: FilterConfig, queryValue?: string): HTMLElement {
    const host = document.createElement('div');
    const app = createApp(TableFilter, { filter, queryValue });
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
        expect(Array.from(row.children).map((child) => child.tagName)).toEqual(['SELECT', 'INPUT', 'INPUT', 'BUTTON']);
    });
});
