export type AvatarTone = 'planned' | 'in-progress' | 'done' | 'warning' | 'neutral';

const TONES: readonly AvatarTone[] = ['planned', 'in-progress', 'done', 'warning', 'neutral'];

export function avatarTone(name: string): AvatarTone {
    let hash = 0;

    for (const char of name.trim().toLowerCase()) {
        hash = (hash * 31 + (char.codePointAt(0) ?? 0)) >>> 0;
    }

    return TONES[hash % TONES.length] as AvatarTone;
}

export const AVATAR_TONE_CLASSES: Record<AvatarTone, string> = {
    planned: 'bg-(--kg-chip-planned-bg) text-(--kg-chip-planned-fg)',
    'in-progress': 'bg-(--kg-chip-in-progress-bg) text-(--kg-chip-in-progress-fg)',
    done: 'bg-(--kg-chip-done-bg) text-(--kg-chip-done-fg)',
    warning: 'bg-(--kg-chip-warning-bg) text-(--kg-chip-warning-fg)',
    neutral: 'bg-(--kg-chip-neutral-bg) text-(--kg-chip-neutral-fg)',
};
