import { format, isSameDay, parseISO } from 'date-fns';
import { localeTag } from '@/utils/money';

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

const MONTH_PATTERN = /^(\d{4})-(\d{2})$/;

function monthKey(date: Date): string {
    return format(date, 'yyyy-MM');
}

export function formatMonth(value: string | null | undefined): string {
    const match = value ? MONTH_PATTERN.exec(value.slice(0, 7)) : null;
    if (!match) return '—';

    return `${Number(match[2])}/${match[1]}`;
}

export function previousMonth(today: Date = new Date()): string {
    return monthKey(new Date(today.getFullYear(), today.getMonth() - 1, 1));
}

export function monthOptions(
    locale: string,
    count = 18,
    include: string | null = null,
    today: Date = new Date(),
): { value: string; label: string }[] {
    const formatter = new Intl.DateTimeFormat(localeTag(locale), { month: 'long', year: 'numeric' });
    const values = Array.from({ length: count }, (_, i) =>
        monthKey(new Date(today.getFullYear(), today.getMonth() - i, 1)),
    );

    if (include && MONTH_PATTERN.test(include) && !values.includes(include)) {
        values.push(include);
        values.sort().reverse();
    }

    return values.map((value) => {
        const [year, month] = value.split('-').map(Number);

        return { value, label: formatter.format(new Date(year as number, (month as number) - 1, 1)) };
    });
}
