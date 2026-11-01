import { describe, expect, it } from 'vitest';
import { KG_ICONS, NAV_ICONS } from './kegosIcons';

describe('kegosIcons', () => {
    it('gives every nav icon an outline and a filled variant', () => {
        for (const icons of Object.values(NAV_ICONS)) {
            expect(icons.outline).toBeTruthy();
            expect(icons.filled).toBeTruthy();
        }
    });

    it('exposes admin action icons', () => {
        for (const icon of Object.values(KG_ICONS.adminAction)) {
            expect(icon).toBeTruthy();
        }
    });
});
