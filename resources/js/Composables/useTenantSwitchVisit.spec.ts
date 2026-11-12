import { beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    router: { post: (...args: unknown[]) => post(...args), visit: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: { tenant: { active: { id: 't1' }, available: [] } } }),
}));

import { useTenantSwitchVisit } from './useTenantSwitchVisit';

describe('useTenantSwitchVisit', () => {
    beforeEach(() => post.mockClear());

    it('posts the exact path without keep_module by default', () => {
        useTenantSwitchVisit().navigate('t2', '/quotes/5');

        expect(post.mock.calls[0][0]).toBe('/tenants/t2/switch');
        expect(post.mock.calls[0][1]).toEqual({ redirect_to: '/quotes/5' });
    });

    it('sends keep_module when asked', () => {
        useTenantSwitchVisit().navigate('t2', '/quotes/5', { keepModule: true });

        expect(post.mock.calls[0][1]).toEqual({ redirect_to: '/quotes/5', keep_module: true });
    });
});
