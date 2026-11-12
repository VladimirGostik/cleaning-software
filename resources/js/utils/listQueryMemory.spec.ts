import { beforeEach, describe, expect, it } from 'vitest';
import { clearAll, pickListParams, restore, save, storageKey } from './listQueryMemory';

const LIST_PATHS = ['/invoices', '/quotes'];

describe('listQueryMemory', () => {
    beforeEach(() => window.sessionStorage.clear());

    it('keeps only allowed params', () => {
        const picked = pickListParams(
            'filter[status]=sent&sort=-date&page=2&per_page=25&view=board&task=1&edit=billing&quote_id=9&billing_period=monthly',
        );
        expect([...picked.keys()]).toEqual(['filter[status]', 'sort', 'page', 'per_page', 'view']);
    });

    it('keys by tenant and path', () => {
        expect(storageKey('t1', '/quotes')).toBe('kg:list-query:t1:/quotes');
    });

    it('saves a list path query and restores it onto a bare href', () => {
        save('t1', '/invoices?filter[status]=sent&sort=-date&task=5', LIST_PATHS);
        expect(window.sessionStorage.getItem(storageKey('t1', '/invoices'))).toBe('filter[status]=sent&sort=-date');
        expect(restore('t1', '/invoices', LIST_PATHS)).toBe('/invoices?filter[status]=sent&sort=-date');
    });

    it('does not touch non list paths', () => {
        save('t1', '/clients?sort=name', LIST_PATHS);
        expect(window.sessionStorage.length).toBe(0);
        expect(restore('t1', '/clients', LIST_PATHS)).toBe('/clients');
    });

    it('keeps an href that already has a query', () => {
        save('t1', '/invoices?page=3', LIST_PATHS);
        expect(restore('t1', '/invoices?page=1', LIST_PATHS)).toBe('/invoices?page=1');
    });

    it('removes the key when nothing allowed remains', () => {
        save('t1', '/invoices?page=3', LIST_PATHS);
        save('t1', '/invoices?task=5', LIST_PATHS);
        expect(window.sessionStorage.getItem(storageKey('t1', '/invoices'))).toBeNull();
        expect(restore('t1', '/invoices', LIST_PATHS)).toBe('/invoices');
    });

    it('keeps memory per tenant', () => {
        save('t1', '/quotes?page=2', LIST_PATHS);
        expect(restore('t2', '/quotes', LIST_PATHS)).toBe('/quotes');
    });

    it('clearAll removes only list memory keys', () => {
        save('t1', '/quotes?page=2', LIST_PATHS);
        window.sessionStorage.setItem('other', 'x');
        clearAll();
        expect(window.sessionStorage.getItem('other')).toBe('x');
        expect(window.sessionStorage.length).toBe(1);
    });
});
