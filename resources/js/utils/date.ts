import { format, isSameDay, parseISO } from 'date-fns';

const DATETIME_FORMAT = 'd.M.y HH:mm:ss';
const DATE_FORMAT = 'd.M.y';
const SHORT_TIME_FORMAT = 'HH:mm';
const SHORT_DATE_FORMAT = 'd.M.';

export function formatDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—';
    const date = typeof value === 'string' ? parseISO(value) : value;
    return format(date, DATETIME_FORMAT);
}

export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return '—';
    const date = typeof value === 'string' ? parseISO(value) : value;
    return format(date, DATE_FORMAT);
}

// Notification bell rows — compact timestamp: 'HH:mm' for today, 'd.M.' otherwise.
export function formatShortDatetime(value: string | Date | null | undefined): string {
    if (!value) return '—';
    const date = typeof value === 'string' ? parseISO(value) : value;
    return isSameDay(date, new Date()) ? format(date, SHORT_TIME_FORMAT) : format(date, SHORT_DATE_FORMAT);
}

export function toDateInputValue(value: string | Date | null | undefined): string {
    if (!value) return '';
    const date = typeof value === 'string' ? parseISO(value) : value;
    return format(date, 'yyyy-MM-dd');
}

export function toDatetimeInputValue(value: string | Date | null | undefined): string {
    if (!value) return '';
    const date = typeof value === 'string' ? parseISO(value) : value;
    return format(date, "yyyy-MM-dd'T'HH:mm");
}
