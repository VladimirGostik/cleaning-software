import { router } from '@inertiajs/vue3';

const INTERACTIVE_SELECTOR = 'a, button, input, select, textarea, label, [role="button"]';

export function isInteractiveTarget(event: Event): boolean {
    const target = event.target as HTMLElement | null;
    return !!target?.closest?.(INTERACTIVE_SELECTOR);
}

export function hasTextSelection(): boolean {
    const selection = window.getSelection();
    return !!selection && selection.toString().length > 0;
}

export function useRowLink() {
    function rowClass(href: string | null): string {
        return href ? 'cursor-pointer' : '';
    }

    function onClick(href: string | null, event: MouseEvent, open?: () => void): void {
        if (!href || isInteractiveTarget(event) || hasTextSelection()) return;

        if (event.metaKey || event.ctrlKey) {
            window.open(href, '_blank', 'noopener');
            return;
        }

        if (open) open();
        else router.visit(href);
    }

    function onAuxClick(href: string | null, event: MouseEvent): void {
        if (event.button !== 1 || !href || isInteractiveTarget(event) || hasTextSelection()) return;

        window.open(href, '_blank', 'noopener');
    }

    return { rowClass, onClick, onAuxClick };
}
