// Single knowledge source for job status visual treatment — consumed by JobStatusBadge (DaisyUI
// badge class) and JobCalendar (FullCalendar event colours via the matching CSS custom property).
export const JOB_STATUS_STYLE: Record<App.Enums.JobStatusEnum, { badge: string; color: string; text: string }> = {
    unassigned: { badge: 'badge-warning', color: 'var(--color-warning)', text: 'var(--color-warning-content)' },
    planned: { badge: 'badge-info', color: 'var(--color-info)', text: 'var(--color-info-content)' },
    in_progress: { badge: 'badge-primary', color: 'var(--color-primary)', text: 'var(--color-primary-content)' },
    completed: { badge: 'badge-success', color: 'var(--color-success)', text: 'var(--color-success-content)' },
    unapproved: { badge: 'badge-error', color: 'var(--color-error)', text: 'var(--color-error-content)' },
    cancelled: { badge: 'badge-neutral', color: 'var(--color-neutral)', text: 'var(--color-neutral-content)' },
};
