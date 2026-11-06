export interface PopoverSize {
    width: number;
    height: number;
}

export interface PopoverPlacement {
    top: number;
    left: number;
    placement: 'bottom' | 'top';
}

const GAP = 4;
const MARGIN = 8;

export function popoverPosition(
    anchor: { top: number; bottom: number; left: number },
    panel: PopoverSize,
    viewport: PopoverSize,
): PopoverPlacement {
    const roomBelow = viewport.height - anchor.bottom - GAP - MARGIN;
    const roomAbove = anchor.top - GAP - MARGIN;
    const placement = roomBelow >= panel.height || roomBelow >= roomAbove ? 'bottom' : 'top';

    const top = placement === 'bottom' ? anchor.bottom + GAP : anchor.top - GAP - panel.height;
    const maxLeft = Math.max(MARGIN, viewport.width - panel.width - MARGIN);
    const left = Math.min(Math.max(anchor.left, MARGIN), maxLeft);

    return { top: Math.max(top, MARGIN), left, placement };
}
