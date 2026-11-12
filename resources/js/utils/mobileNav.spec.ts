import { describe, expect, it } from 'vitest';
import { mobileNavItems } from './mobileNav';

function item(key: string, children: App.Data.NavigationItemData[] = []): App.Data.NavigationItemData {
    return { key, label: key, href: `/${key}`, icon: 'dashboard', order: 0, children };
}

describe('mobileNavItems', () => {
    it('keeps the given order and caps at four leaves', () => {
        const nav = ['a', 'b', 'c', 'd', 'e', 'f'].map((key) => item(key));
        expect(mobileNavItems(nav).map((i) => i.key)).toEqual(['a', 'b', 'c', 'd']);
    });

    it('ignores group entries', () => {
        const nav = [item('a'), item('group', [item('child')]), item('b')];
        expect(mobileNavItems(nav).map((i) => i.key)).toEqual(['a', 'b']);
    });

    it('returns fewer when fewer leaves exist', () => {
        expect(mobileNavItems([item('a')])).toHaveLength(1);
        expect(mobileNavItems([])).toEqual([]);
    });

    it('honours a custom limit', () => {
        const nav = ['a', 'b', 'c'].map((key) => item(key));
        expect(mobileNavItems(nav, 2)).toHaveLength(2);
    });
});
