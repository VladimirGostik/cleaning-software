import type { StatusTone } from '@/Components/statusTone';

export const JOB_STATUS_TONE: Record<App.Enums.JobStatusEnum, StatusTone> = {
    unassigned: 'warning',
    planned: 'planned',
    in_progress: 'in_progress',
    completed: 'done',
    unapproved: 'danger',
    cancelled: 'neutral',
};

export function jobStatusColors(status: App.Enums.JobStatusEnum): { bg: string; fg: string } {
    const token = JOB_STATUS_TONE[status].replace('_', '-');

    return { bg: `var(--kg-chip-${token}-bg)`, fg: `var(--kg-chip-${token}-fg)` };
}
