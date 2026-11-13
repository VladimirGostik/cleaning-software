export interface PopoverSize {
    width: number;
    height: number;
}

export interface PopoverPlacement {
    top: number;
    left: number;
    placement: 'bottom' | 'top' | 'shift';
    maxHeight: number | null;
}

const GAP = 4;
const MARGIN = 8;

export function popoverPosition(
    anchor: { top: number; bottom: number; left: number },
    panel: PopoverSize,
    viewport: PopoverSize,
): PopoverPlacement {
    const maxLeft = Math.max(MARGIN, viewport.width - panel.width - MARGIN);
    const left = Math.min(Math.max(anchor.left, MARGIN), maxLeft);
    const limit = viewport.height - 2 * MARGIN;

    if (panel.height > limit) {
        return { top: MARGIN, left, placement: 'shift', maxHeight: limit };
    }

    const roomBelow = viewport.height - anchor.bottom - GAP - MARGIN;
    const roomAbove = anchor.top - GAP - MARGIN;

    if (roomBelow >= panel.height) {
        return { top: anchor.bottom + GAP, left, placement: 'bottom', maxHeight: null };
    }

    if (roomAbove >= panel.height) {
        return { top: anchor.top - GAP - panel.height, left, placement: 'top', maxHeight: null };
    }

    return { top: viewport.height - MARGIN - panel.height, left, placement: 'shift', maxHeight: null };
}
