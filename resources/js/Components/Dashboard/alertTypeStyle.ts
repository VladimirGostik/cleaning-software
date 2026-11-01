import type { StatusTone } from '@/Components/statusTone';

export const ALERT_TYPE_STYLE: Record<App.Enums.DashboardAlertTypeEnum, { dot: string; tone: StatusTone }> = {
    overdue_invoice: { dot: 'bg-error', tone: 'danger' },
    supplier_incomplete: { dot: 'bg-warning', tone: 'warning' },
    unassigned_job: { dot: 'bg-warning', tone: 'warning' },
    contract_expiring: { dot: 'bg-warning', tone: 'warning' },
    quote_expiring: { dot: 'bg-primary', tone: 'planned' },
};
