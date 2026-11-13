import { describe, expect, it } from 'vitest';
import { AVATAR_TONE_CLASSES, avatarTone } from './avatarTone';

const NAMES = ['Jana Nová', 'Peter Kováč', 'Admin', 'Mária Horváthová', 'Ján Straka', 'Eva', 'Tomáš', 'Zuzana Malá'];

describe('avatarTone', () => {
    it('is stable for the same name regardless of case and spacing', () => {
        expect(avatarTone('Jana Nová')).toBe(avatarTone('Jana Nová'));
        expect(avatarTone('Jana Nová')).toBe(avatarTone('  jana nová '));
    });

    it('spreads people across several tones and never uses danger', () => {
        const tones = new Set(NAMES.map(avatarTone));

        expect(tones.size).toBeGreaterThan(2);
        expect([...tones].every((tone) => tone in AVATAR_TONE_CLASSES)).toBe(true);
        expect(Object.keys(AVATAR_TONE_CLASSES).join()).not.toContain('danger');
    });
});
