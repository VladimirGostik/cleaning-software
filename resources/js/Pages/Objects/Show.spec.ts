import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
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
vi.mock('@/Components/Quotes/RelatedQuotesCard.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Contacts/ContactsList.vue', () => ({ default: { render: () => null } }));
vi.mock('@/Components/Objects/ObjectVisitRecordsCard.vue', async () => {
    const { h: hh } = await import('vue');
    return { default: { render: () => hh('div', { id: 'records' }) } };
});
vi.mock('@/Components/Objects/ObjectVisitItemsCard.vue', async () => {
    const { h: hh } = await import('vue');
    return {
        default: {
            props: ['items', 'canEdit'],
            emits: ['edit'],
            setup:
                (props: { canEdit: boolean }, { emit }: { emit: (e: 'edit') => void }) =>
                () =>
                    hh('button', {
                        id: 'items-card',
                        'data-can-edit': String(props.canEdit),
                        onClick: () => emit('edit'),
                    }),
        },
    };
});
vi.mock('@/Components/Objects/ObjectVisitItemsDrawer.vue', async () => {
    const { h: hh } = await import('vue');
    return {
        default: {
            props: ['open'],
            setup: (props: { open: boolean }) => () =>
                hh('div', { id: 'items-drawer', 'data-open': String(props.open) }),
        },
    };
});

interface VisitProps {
    visitItems: unknown[] | null;
    visitRecords: unknown | null;
}

const noVisitData: VisitProps = { visitItems: null, visitRecords: null };

function render(visit: VisitProps = noVisitData): HTMLElement {
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
                quotes: null,
                canCreateQuote: false,
                ...visit,
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

describe('Objects Show visit cards', () => {
    const withData: VisitProps = {
        visitItems: [{ id: 'i1', name: 'Vstup', price: '10.00', position: 0 }],
        visitRecords: {},
    };

    beforeEach(() => {
        state.url = '/objects/o1';
        state.can = { editObjects: true };
    });

    it('renders neither card nor drawer when the server sends null', () => {
        const host = render();

        expect(host.querySelector('#items-card')).toBeNull();
        expect(host.querySelector('#records')).toBeNull();
        expect(host.querySelector('#items-drawer')).toBeNull();
    });

    it('renders both cards when the server sends the data', () => {
        const host = render(withData);

        expect(host.querySelector('#items-card')).not.toBeNull();
        expect(host.querySelector('#records')).not.toBeNull();
    });

    it('opens the items drawer from the card edit event', async () => {
        const host = render(withData);

        expect((host.querySelector('#items-drawer') as HTMLElement).dataset.open).toBe('false');
        (host.querySelector('#items-card') as HTMLElement).click();
        await nextTick();

        expect((host.querySelector('#items-drawer') as HTMLElement).dataset.open).toBe('true');
    });

    it('lets the card edit only with the edit objects permission', () => {
        state.can = { editObjects: false };

        expect((render(withData).querySelector('#items-card') as HTMLElement).dataset.canEdit).toBe('false');
    });
});
