import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import Show from './Show.vue';

const state = vi.hoisted(() => ({ url: '/objects/o1', can: {} as Record<string, boolean> }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        get url() {
            return state.url;
        },
        props: {
            get can() {
                return state.can;
            },
        },
    }),
    router: { post: vi.fn() },
}));

vi.mock('@/Components/Objects/ObjectFormDrawer.vue', async () => {
    const { h: hh } = await import('vue');
    return {
        default: {
            props: ['open', 'focusSection'],
            setup: (props: { open: boolean; focusSection?: string }) => () =>
                hh('div', {
                    id: 'drawer',
                    'data-open': String(props.open),
                    'data-focus': props.focusSection ?? '',
                }),
        },
    };
});

vi.mock('@/Layouts/Header.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/ConfirmDeleteModal.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Objects/ObjectDetailCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Objects/ObjectBillingCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Objects/ObjectAccessCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Objects/ObjectWorkBreakdownsCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Contracts/RelatedContractsCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Contacts/ContactsList.vue', () => ({ default: { render: () => null } }));

function render(): HTMLElement {
    const host = document.createElement('div');
    createApp({
        render: () =>
            h(Show, {
                object: {
                    id: 'o1',
                    name: 'Dom',
                    is_active: true,
                    special_instructions: null,
                    billing: null,
                    contacts: null,
                },
                clients: [{ id: 'c1', name: 'K' }],
                workBreakdowns: [],
                contracts: null,
                canCreateContract: false,
            } as never),
    })
        .use(createI18n({ legacy: false, locale: 'en', messages: { en: {} }, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

const drawer = (host: HTMLElement) => host.querySelector('#drawer') as HTMLElement;

describe('Objects Show deep link', () => {
    beforeEach(() => {
        state.url = '/objects/o1';
        state.can = { editObjects: true };
    });

    it('opens the drawer with the billing focus for ?edit=billing', () => {
        state.url = '/objects/o1?edit=billing';

        expect(drawer(render()).dataset.open).toBe('true');
        expect(drawer(render()).dataset.focus).toBe('billing');
    });

    it('stays closed without the query', () => {
        expect(drawer(render()).dataset.open).toBe('false');
        expect(drawer(render()).dataset.focus).toBe('');
    });

    it('stays closed without edit permission', () => {
        state.url = '/objects/o1?edit=billing';
        state.can = { editObjects: false };

        expect(drawer(render()).dataset.open).toBe('false');
    });
});
