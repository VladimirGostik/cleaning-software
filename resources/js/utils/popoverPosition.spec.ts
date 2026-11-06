import { describe, expect, it } from 'vitest';
import { popoverPosition } from './popoverPosition';

const viewport = { width: 1000, height: 800 };
const panel = { width: 200, height: 150 };

describe('popoverPosition', () => {
    it('opens below the anchor when there is room', () => {
        const result = popoverPosition({ top: 100, bottom: 130, left: 300 }, panel, viewport);

        expect(result).toEqual({ top: 134, left: 300, placement: 'bottom' });
    });

    it('flips above near the bottom edge', () => {
        const result = popoverPosition({ top: 700, bottom: 730, left: 300 }, panel, viewport);

        expect(result.placement).toBe('top');
        expect(result.top).toBe(700 - 4 - 150);
    });

    it('stays below when neither side fits but below has more room', () => {
        const result = popoverPosition({ top: 100, bottom: 130, left: 10 }, { width: 200, height: 900 }, viewport);

        expect(result.placement).toBe('bottom');
    });

    it('clamps into the viewport horizontally', () => {
        expect(popoverPosition({ top: 100, bottom: 130, left: 950 }, panel, viewport).left).toBe(792);
        expect(popoverPosition({ top: 100, bottom: 130, left: -40 }, panel, viewport).left).toBe(8);
    });
});
