// Single knowledge source for dashboard alert type colour — exhaustive Record over the full
// DashboardAlertTypeEnum union so TS catches a missing member (precedent: notificationTypeStyle.ts).
export const ALERT_TYPE_STYLE: Record<App.Enums.DashboardAlertTypeEnum, { dot: string; badge: string }> = {
    overdue_invoice: { dot: 'bg-error', badge: 'badge-error' },
    supplier_incomplete: { dot: 'bg-warning', badge: 'badge-warning' },
    unassigned_job: { dot: 'bg-warning', badge: 'badge-warning' },
    contract_expiring: { dot: 'bg-warning', badge: 'badge-warning' },
    quote_expiring: { dot: 'bg-info', badge: 'badge-info' },
};
