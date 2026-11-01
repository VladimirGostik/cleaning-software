import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readFileSync('resources/css/app.css', 'utf-8');

const themeBlock = css.slice(css.indexOf("[data-theme='app-theme']"), css.indexOf('@theme'));

const vars = new Map<string, string>();
for (const m of themeBlock.matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
    vars.set(m[1], m[2].trim());
}
for (const m of css.slice(css.indexOf('@theme')).matchAll(/(--color-[\w-]+):\s*(#[0-9a-fA-F]{6});/g)) {
    if (!vars.has(m[1])) vars.set(m[1], m[2]);
}

function luminance(hex: string): number {
    const [r, g, b] = [1, 3, 5]
        .map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string): number {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

const color = (name: string): string => {
    const value = vars.get(`--color-${name}`);
    if (!value) throw new Error(`missing --color-${name}`);
    return value;
};

describe('app-theme contract', () => {
    it('keeps ink text on the orange CTA', () => {
        expect(color('secondary-content').toLowerCase()).toBe('#111c18');
    });

    it.each(['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'])(
        '%s / %s-content reaches 4.5:1',
        (name) => {
            expect(contrast(color(name), color(`${name}-content`))).toBeGreaterThanOrEqual(4.5);
        },
    );

    it.each(['accent', 'primary', 'error', 'muted'])('%s text on base-100 reaches 4.5:1', (name) => {
        expect(contrast(color(name), color('base-100'))).toBeGreaterThanOrEqual(4.5);
    });

    it.each(['planned', 'in-progress', 'done', 'warning', 'danger', 'neutral'])(
        'chip %s fg on bg reaches 4.5:1',
        (tone) => {
            const fg = vars.get(`--kg-chip-${tone}-fg`);
            const bg = vars.get(`--kg-chip-${tone}-bg`);
            expect(fg).toBeDefined();
            expect(bg).toBeDefined();
            expect(contrast(fg as string, bg as string)).toBeGreaterThanOrEqual(4.5);
        },
    );

    it('uses the v2 shape tokens', () => {
        expect(vars.get('--radius-box')).toBe('1rem');
        expect(vars.get('--radius-field')).toBe('0.625rem');
        expect(vars.get('--depth')).toBe('0');
    });
});
