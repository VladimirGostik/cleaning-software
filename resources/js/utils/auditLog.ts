import { formatDate, formatDatetime } from '@/utils/date';

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const ISO_DATETIME = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/;
const REVERT_EVENTS = ['created', 'updated', 'deleted', 'restored'];

export function formatAuditValue(value: unknown, t: (key: string) => string): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return t(value ? 'yes' : 'no');
    if (typeof value === 'string') {
        if (ISO_DATETIME.test(value)) return formatDatetime(value);
        if (ISO_DATE.test(value)) return formatDate(value);
        return value;
    }
    if (typeof value === 'number') return String(value);
    return JSON.stringify(value);
}

export function revertConfirmKey(event: string | null): string {
    return event !== null && REVERT_EVENTS.includes(event)
        ? `audit_revert_confirm_${event}`
        : 'audit_revert_confirm_generic';
}

export function revertSubjectLabel(
    item: Pick<App.Data.ActivityLogListItemData, 'subject_label' | 'subject_type' | 'subject_id'>,
): string {
    return item.subject_label ?? `${item.subject_type ?? '-'} #${item.subject_id ?? '-'}`;
}
