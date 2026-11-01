import { format, isSameDay, parseISO } from 'date-fns';

export const DATE_FORMAT = 'd. M. yyyy';
export const DATETIME_FORMAT = 'd. M. yyyy H:mm';
export const SHORT_TIME_FORMAT = 'H:mm';
export const SHORT_DATE_FORMAT = 'd. M.';

function toDate(value: string | Date): Date {
    return typeof value === 'string' ? parseISO(value) : value;
}

export function formatDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—';
    return format(toDate(value), DATETIME_FORMAT);
}

export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return '—';
    return format(toDate(value), DATE_FORMAT);
}

export function formatShortDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—';
    const date = toDate(value);
    return isSameDay(date, new Date()) ? format(date, SHORT_TIME_FORMAT) : format(date, SHORT_DATE_FORMAT);
}

export function toDateInputValue(value: string | Date | null | undefined): string {
    if (!value) return '';
    return format(toDate(value), 'yyyy-MM-dd');
}

export function toDatetimeInputValue(value: string | Date | null | undefined): string {
    if (!value) return '';
    return format(toDate(value), "yyyy-MM-dd'T'HH:mm");
}
