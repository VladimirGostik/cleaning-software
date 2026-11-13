import {
    addDays,
    addMonths,
    addYears,
    endOfWeek,
    format,
    isValid,
    nextMonday,
    parseISO,
    startOfMonth,
    startOfWeek,
    subMonths,
    subYears,
} from 'date-fns';

export const DEFAULT_DUE_TIME = '12:00';

const ISO_DATE = 'yyyy-MM-dd';
const WEEK = { weekStartsOn: 1 } as const;

export function toIsoDate(date: Date): string {
    return format(date, ISO_DATE);
}

export function fromIsoDate(value: string): Date {
    return parseISO(value);
}

export function monthMatrix(month: Date): Date[][] {
    const first = startOfWeek(startOfMonth(month), WEEK);

    return Array.from({ length: 6 }, (_, row) => Array.from({ length: 7 }, (_, col) => addDays(first, row * 7 + col)));
}

export function moveFocus(day: Date, key: string, shift = false): Date {
    switch (key) {
        case 'ArrowLeft':
            return addDays(day, -1);
        case 'ArrowRight':
            return addDays(day, 1);
        case 'ArrowUp':
            return addDays(day, -7);
        case 'ArrowDown':
            return addDays(day, 7);
        case 'Home':
            return startOfWeek(day, WEEK);
        case 'End':
            return endOfWeek(day, WEEK);
        case 'PageUp':
            return shift ? subYears(day, 1) : subMonths(day, 1);
        case 'PageDown':
            return shift ? addYears(day, 1) : addMonths(day, 1);
        default:
            return day;
    }
}

function buildDate(year: number, month: number, day: number): string | null {
    const date = new Date(year, month - 1, day);
    if (!isValid(date) || date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return toIsoDate(date);
}

export function parseTypedDate(text: string, today: Date): string | null {
    const value = text.replace(/\s+/g, '');

    const compact = /^(\d{2})(\d{2})(\d{4})$/.exec(value);
    if (compact) return buildDate(Number(compact[3]), Number(compact[2]), Number(compact[1]));

    const dotted = /^(\d{1,2})\.(\d{1,2})(?:\.(\d{4}|\d{2})?)?$/.exec(value);
    if (!dotted) return null;

    const year =
        dotted[3] === undefined
            ? today.getFullYear()
            : dotted[3].length === 2
              ? 2000 + Number(dotted[3])
              : Number(dotted[3]);

    return buildDate(year, Number(dotted[2]), Number(dotted[1]));
}

export function parseTypedTime(text: string): string | null {
    const value = text.replace(/\s+/g, '');
    let hours: number;
    let minutes: number;

    const hourOnly = /^(\d{1,2})$/.exec(value);
    const separated = /^(\d{1,2})[:.](\d{2})$/.exec(value);
    const compact = /^(\d{1,2})(\d{2})$/.exec(value);

    if (hourOnly) {
        hours = Number(hourOnly[1]);
        minutes = 0;
    } else if (separated) {
        hours = Number(separated[1]);
        minutes = Number(separated[2]);
    } else if (compact) {
        hours = Number(compact[1]);
        minutes = Number(compact[2]);
    } else {
        return null;
    }

    if (hours > 23 || minutes > 59) return null;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

export function withinRange(value: string, min?: string, max?: string): boolean {
    if (min && value < min) return false;
    if (max && value > max) return false;

    return true;
}

export function tomorrowOf(today: Date): string {
    return toIsoDate(addDays(today, 1));
}

export function nextMondayOf(today: Date): string {
    return toIsoDate(nextMonday(today));
}
