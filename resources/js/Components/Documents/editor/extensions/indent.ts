import { Extension } from '@tiptap/vue-3';

export const MAX_INDENT_LEVEL = 8;
const EM_PER_LEVEL = 2;
const PX_PER_LEVEL = 32;
const MM_PER_LEVEL = 10;

function clampIndent(level: number): number {
    return Math.min(MAX_INDENT_LEVEL, Math.max(0, Math.round(level)));
}

export function parseIndentFromMarginLeft(value: string | null | undefined): number {
    const match = /^\s*(-?\d*\.?\d+)\s*(em|px|mm)?\s*$/i.exec(value ?? '');
    if (!match) return 0;
    const amount = Number(match[1]);
    const unit = (match[2] ?? '').toLowerCase();
    if (unit === 'em') return clampIndent(amount / EM_PER_LEVEL);
    if (unit === 'px') return clampIndent(amount / PX_PER_LEVEL);
    if (unit === 'mm') return clampIndent(amount / MM_PER_LEVEL);
    return 0;
}

function marginLeftOf(element: HTMLElement): string | null {
    const style = element.getAttribute('style') ?? '';
    const match = /(?:^|;)\s*margin-left\s*:\s*([^;]+)/i.exec(style);

    return match?.[1]?.trim() ?? null;
}

export const Indent = Extension.create({
    name: 'indent',
    priority: 110,

    addGlobalAttributes() {
        return [
            {
                types: ['paragraph', 'heading'],
                attributes: {
                    indent: {
                        default: 0,
                        parseHTML: (element: HTMLElement) => parseIndentFromMarginLeft(marginLeftOf(element)),
                        renderHTML: (attributes: Record<string, unknown>) => {
                            const level = Number(attributes.indent ?? 0);

                            return level > 0 ? { style: `margin-left: ${level * EM_PER_LEVEL}em` } : {};
                        },
                    },
                },
            },
        ];
    },
});
