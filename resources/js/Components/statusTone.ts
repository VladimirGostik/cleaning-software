import type { Component } from 'vue';
import { KG_ICONS } from '@/icons/kegosIcons';

export type StatusTone = 'planned' | 'in_progress' | 'done' | 'warning' | 'danger' | 'neutral';

export const TONE_ICON: Record<StatusTone, Component | undefined> = {
    planned: KG_ICONS.status.planned,
    in_progress: KG_ICONS.status.inProgress,
    done: KG_ICONS.status.done,
    warning: KG_ICONS.status.warning,
    danger: KG_ICONS.status.danger,
    neutral: undefined,
};
