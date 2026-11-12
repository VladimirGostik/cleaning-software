import { describe, expect, it } from 'vitest';
import { KG_ICONS, NAV_ICONS } from './kegosIcons';

describe('kegosIcons', () => {
    it('gives every nav icon one component', () => {
        for (const icon of Object.values(NAV_ICONS)) {
            expect(icon).toBeTruthy();
            expect(typeof icon === 'object' || typeof icon === 'function').toBe(true);
            expect(icon).not.toHaveProperty('outline');
            expect(icon).not.toHaveProperty('filled');
        }
    });

    it('exposes admin action icons', () => {
        for (const icon of Object.values(KG_ICONS.adminAction)) {
            expect(icon).toBeTruthy();
        }
    });
});
