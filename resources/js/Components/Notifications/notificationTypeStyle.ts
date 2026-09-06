// Single knowledge source for notification type colour — exhaustive Record over the full
// NotificationTypeEnum union so TS catches a missing member (precedent: jobStatusStyle.ts).
// `badge` = centre table badge (NotificationTypeBadge.vue); `dot` = compact bell row indicator
// (NotificationItem.vue) — same semantic colour, different DaisyUI utility.
export const NOTIFICATION_TYPE_STYLE: Record<App.Enums.NotificationTypeEnum, { badge: string; dot: string }> = {
    'invitation.created': { badge: 'badge-ghost', dot: 'bg-base-300' },
    'invoice.issued': { badge: 'badge-info', dot: 'bg-info' },
    'invoice.overdue': { badge: 'badge-error', dot: 'bg-error' },
    'contract.expiring': { badge: 'badge-warning', dot: 'bg-warning' },
    'contract.expired': { badge: 'badge-error', dot: 'bg-error' },
    'quote.sent': { badge: 'badge-info', dot: 'bg-info' },
    'quote.expiring': { badge: 'badge-warning', dot: 'bg-warning' },
    'quote.expired': { badge: 'badge-error', dot: 'bg-error' },
};
