import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const post = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    router: { post: (...args: unknown[]) => post(...args), visit: vi.fn(), reload: vi.fn() },
    usePage: () => ({
        props: {
            canCreateTenant: false,
            tenant: { active: { id: 't1' }, available: [] },
        },
    }),
}));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

vi.mock('@/Components/AnchoredPopover.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            render() {
                return h('div', this.$slots.default?.());
            },
        }),
    };
});

import TenantSwitcher from './TenantSwitcher.vue';

describe('TenantSwitcher', () => {
    beforeEach(() => {
        post.mockClear();
        window.history.pushState({}, '', '/quotes?filter[status]=sent');
    });

    it('posts the current path and query as redirect_to with keep_module', async () => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const app = createApp(TenantSwitcher, {
            tenant: {
                active: { id: 't1', name: 'One', color: null },
                available: [
                    { id: 't1', name: 'One', color: null },
                    { id: 't2', name: 'Two', color: null },
                ],
            },
        });
        app.mount(host);
        await nextTick();

        const items = host.querySelectorAll<HTMLElement>('button[role="menuitem"]');
        items[1].click();

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0][0]).toBe('/tenants/t2/switch');
        expect(post.mock.calls[0][1]).toEqual({ redirect_to: '/quotes?filter[status]=sent', keep_module: true });
    });
});
