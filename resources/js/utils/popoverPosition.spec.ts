import { describe, expect, it } from 'vitest';
import { popoverPosition } from './popoverPosition';

const viewport = { width: 1000, height: 800 };
const panel = { width: 200, height: 150 };

describe('popoverPosition', () => {
    it('opens below the anchor when there is room', () => {
        const result = popoverPosition({ top: 100, bottom: 130, left: 300 }, panel, viewport);

        expect(result).toEqual({ top: 134, left: 300, placement: 'bottom', maxHeight: null });
    });

    it('opens upward near the bottom edge when there is room above', () => {
        const result = popoverPosition({ top: 700, bottom: 730, left: 300 }, panel, viewport);

        expect(result.placement).toBe('top');
        expect(result.top).toBe(700 - 4 - 150);
        expect(result.maxHeight).toBeNull();
    });

    it('shifts up into the viewport when neither side fits', () => {
        const result = popoverPosition({ top: 300, bottom: 330, left: 10 }, { width: 200, height: 500 }, viewport);

        expect(result.placement).toBe('shift');
        expect(result.top).toBe(800 - 8 - 500);
        expect(result.top + 500).toBeLessThanOrEqual(792);
        expect(result.maxHeight).toBeNull();
    });

    it('caps the height to the viewport minus the margin as a last resort', () => {
        const small = { width: 390, height: 844 };
        const result = popoverPosition({ top: 330, bottom: 370, left: 10 }, { width: 344, height: 536 + 400 }, small);

        expect(result.placement).toBe('shift');
        expect(result.top).toBe(8);
        expect(result.maxHeight).toBe(844 - 16);
    });

    it('keeps a 536 px panel inside a 390x844 viewport when the anchor sits at 358', () => {
        const result = popoverPosition(
            { top: 320, bottom: 354, left: 20 },
            { width: 344, height: 536 },
            { width: 390, height: 844 },
        );

        expect(result.top).toBeGreaterThanOrEqual(8);
        expect(result.top + 536).toBeLessThanOrEqual(844 - 8);
    });

    it('clamps into the viewport horizontally', () => {
        expect(popoverPosition({ top: 100, bottom: 130, left: 950 }, panel, viewport).left).toBe(792);
        expect(popoverPosition({ top: 100, bottom: 130, left: -40 }, panel, viewport).left).toBe(8);
    });
});
