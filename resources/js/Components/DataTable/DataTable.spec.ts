import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import DataTable from './DataTable.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: 'a',
    router: { visit: vi.fn(), get: vi.fn() },
    usePage: () => ({ url: '/', props: {} }),
}));

const rows = {
    data: [
        { id: 'a', name: 'Alpha' },
        { id: 'b', name: 'Beta' },
    ],
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 2,
    from: 1,
    to: 2,
    prev_page_url: null,
    next_page_url: null,
    links: [],
} as never;

function mountTable() {
    const selected = ref<string[]>([]);
    const host = document.createElement('div');
    document.body.appendChild(host);
    const i18n = createI18n({
        legacy: false,
        locale: 'sk',
        missingWarn: false,
        fallbackWarn: false,
        messages: { sk: {} },
    });
    const Root = defineComponent({
        setup: () => () =>
            h(DataTable as never, {
                rows,
                columns: [{ key: 'name', label: 'Name' }],
                rowKey: 'id',
                selectable: true,
                selectAllLabel: 'Select all',
                enableFilters: false,
                enablePagination: false,
                selected: selected.value,
                'onUpdate:selected': (value: string[]) => {
                    selected.value = value;
                },
            }),
    });
    createApp(Root).use(i18n).mount(host);
    return { host, selected };
}

describe('DataTable mobile select-all', () => {
    it('renders a labelled checkbox that toggles every row', async () => {
        const { host, selected } = mountTable();
        const control = host.querySelector<HTMLInputElement>('[data-autom="select-all-mobile"] input[type="checkbox"]');

        expect(control).not.toBeNull();
        expect(host.querySelector('[data-autom="select-all-mobile"]')?.textContent).toContain('Select all');

        control!.checked = true;
        control!.dispatchEvent(new Event('change'));
        await Promise.resolve();
        expect(selected.value).toEqual(['a', 'b']);

        control!.checked = false;
        control!.dispatchEvent(new Event('change'));
        await Promise.resolve();
        expect(selected.value).toEqual([]);
    });
});
