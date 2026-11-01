import type { StatusTone } from '@/Components/statusTone';

export const NOTIFICATION_TYPE_STYLE: Record<App.Enums.NotificationTypeEnum, { tone: StatusTone; dot: string }> = {
    'invitation.created': { tone: 'neutral', dot: 'bg-base-300' },
    'invoice.issued': { tone: 'planned', dot: 'bg-primary' },
    'invoice.overdue': { tone: 'danger', dot: 'bg-error' },
    'contract.expiring': { tone: 'warning', dot: 'bg-warning' },
    'contract.expired': { tone: 'danger', dot: 'bg-error' },
    'quote.sent': { tone: 'planned', dot: 'bg-primary' },
    'quote.expiring': { tone: 'warning', dot: 'bg-warning' },
    'quote.expired': { tone: 'danger', dot: 'bg-error' },
    'task.due': { tone: 'warning', dot: 'bg-warning' },
    'task.assigned': { tone: 'planned', dot: 'bg-primary' },
    'task.commented': { tone: 'planned', dot: 'bg-primary' },
    'task.ai_run': { tone: 'planned', dot: 'bg-primary' },
};
