export interface CalendarRange {
    from: string;
    to: string;
}

export function sameRange(a: CalendarRange | null, b: CalendarRange | null): boolean {
    return a !== null && b !== null && a.from === b.from && a.to === b.to;
}
