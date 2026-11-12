import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readFileSync('resources/css/app.css', 'utf-8');

const themeBlock = css.slice(css.indexOf("[data-theme='app-theme']"), css.indexOf('@theme'));

interface Rule {
    selector: string;
    decls: Map<string, string>;
}

function splitDeclarations(body: string): string[] {
    const out: string[] = [];
    let depth = 0;
    let quote = '';
    let current = '';
    for (const ch of body) {
        if (quote) {
            if (ch === quote) quote = '';
        } else if (ch === '"' || ch === "'") {
            quote = ch;
        } else if (ch === '(') {
            depth++;
        } else if (ch === ')') {
            depth--;
        } else if (ch === ';' && depth === 0) {
            out.push(current);
            current = '';
            continue;
        }
        current += ch;
    }
    out.push(current);
    return out;
}

function parseRules(src: string): Rule[] {
    const rules: Rule[] = [];
    let i = 0;
    while (i < src.length) {
        const open = src.indexOf('{', i);
        const semi = src.indexOf(';', i);
        if (open === -1) break;
        if (semi !== -1 && semi < open) {
            i = semi + 1;
            continue;
        }
        let depth = 1;
        let j = open + 1;
        while (j < src.length && depth > 0) {
            if (src[j] === '{') depth++;
            else if (src[j] === '}') depth--;
            j++;
        }
        const selector = src.slice(i, open).trim();
        const body = src.slice(open + 1, j - 1);
        if (body.includes('{')) {
            rules.push(...parseRules(body));
        } else {
            const decls = new Map<string, string>();
            for (const decl of splitDeclarations(body)) {
                const colon = decl.indexOf(':');
                if (colon === -1) continue;
                decls.set(decl.slice(0, colon).trim(), decl.slice(colon + 1).trim());
            }
            rules.push({ selector, decls });
        }
        i = j;
    }
    return rules;
}

function blockBody(src: string, header: string): string {
    const open = src.indexOf('{', src.indexOf(header));
    let depth = 1;
    let j = open + 1;
    while (j < src.length && depth > 0) {
        if (src[j] === '{') depth++;
        else if (src[j] === '}') depth--;
        j++;
    }
    return src.slice(open + 1, j - 1);
}

const isZero = (v: string) => /^(0|0px|none|var\(--border\))$/.test(v);

function drawsBorder(prop: string, value: string): boolean {
    const side = '(-(top|right|bottom|left|inline|block)(-(start|end))?)?';
    if (new RegExp(`^border${side}$`).test(prop)) return !isZero(value.split(/\s+/)[0]);
    if (new RegExp(`^border${side}-width$`).test(prop)) return !value.split(/\s+/).every(isZero);
    if (new RegExp(`^border${side}-color$`).test(prop)) return value !== 'transparent';
    return false;
}

const COLOUR_SNAPSHOT: Record<string, string> = {
    '--color-base-100': '#ffffff',
    '--color-base-200': '#f6f7f6',
    '--color-base-300': '#e9ecea',
    '--color-base-content': '#111c18',
    '--color-primary': '#0e7c5f',
    '--color-primary-content': '#ffffff',
    '--color-secondary': '#ff5a1f',
    '--color-secondary-content': '#111c18',
    '--color-accent': '#9c3a0e',
    '--color-accent-content': '#ffffff',
    '--color-neutral': '#eef1ef',
    '--color-neutral-content': '#111c18',
    '--color-info': '#0e7c5f',
    '--color-info-content': '#ffffff',
    '--color-success': '#1f6b41',
    '--color-success-content': '#ffffff',
    '--color-warning': '#855600',
    '--color-warning-content': '#ffffff',
    '--color-error': '#b42318',
    '--color-error-content': '#ffffff',
    '--color-muted': '#5a6661',
    '--color-primary-hover': '#0a654d',
    '--color-primary-soft': '#e7f4ef',
    '--color-border-strong': '#7a8581',
    '--color-danger-soft': '#fdecea',
    '--kg-chip-planned-fg': '#0a654d',
    '--kg-chip-planned-bg': '#e7f4ef',
    '--kg-chip-in-progress-fg': '#9c3a0e',
    '--kg-chip-in-progress-bg': '#fff0e8',
    '--kg-chip-done-fg': '#1f6b41',
    '--kg-chip-done-bg': '#e6f4eb',
    '--kg-chip-warning-fg': '#855600',
    '--kg-chip-warning-bg': '#fff5dc',
    '--kg-chip-danger-fg': '#b42318',
    '--kg-chip-danger-bg': '#fdecea',
    '--kg-chip-neutral-fg': '#55605b',
    '--kg-chip-neutral-bg': '#eef1ef',
};

const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');
const rules = parseRules(stripped);
const outsideDocument = rules.filter((r) => !r.selector.includes('.document-'));

const themeBlockVars = new Map<string, string>();
for (const m of blockBody(stripped, "[data-theme='app-theme']").matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
    themeBlockVars.set(m[1], m[2].trim());
}
const themeTokens = new Map<string, string>();
for (const m of blockBody(stripped, '@theme {').matchAll(/(--[\w-]+):\s*([^;]+);/g)) {
    themeTokens.set(m[1], m[2].trim());
}

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

    it('uses the Taskio shape tokens', () => {
        expect(vars.get('--radius-box')).toBe('1.625rem');
        expect(vars.get('--radius-field')).toBe('1rem');
        expect(vars.get('--radius-selector')).toBe('100px');
        expect(vars.get('--border')).toBe('0px');
        expect(vars.get('--depth')).toBe('0');
        expect(vars.get('--noise')).toBe('0');
        expect(vars.get('--kg-sidebar-width')).toBe('15rem');
        expect(vars.get('--kg-nav-item-height')).toBe('2.75rem');
        expect(vars.get('--kg-bottom-nav-height')).toBe('3.375rem');
    });

    it('declares the Taskio type and radius tokens in @theme', () => {
        expect(themeTokens.get('--radius-card')).toBe('1.625rem');
        expect(themeTokens.get('--radius-card-nested')).toBe('1.1875rem');
        expect(themeTokens.get('--radius-tile')).toBe('1rem');
        expect(themeTokens.get('--radius-tile-sm')).toBe('0.875rem');
        expect(themeTokens.get('--radius-pill')).toBe('100px');
        expect(themeTokens.get('--radius-progress')).toBe('0.9375rem');
        expect(themeTokens.get('--text-2xs')).toBe('0.625rem');
        expect(themeTokens.get('--text-xs')).toBe('0.75rem');
        expect(themeTokens.get('--text-stat')).toBe('2rem');
        expect(themeTokens.get('--font-sans')).toBe("'Helvetica Neue', Helvetica, Arial, sans-serif");
    });

    it('has no shadow token in @theme', () => {
        expect([...themeTokens.keys()].filter((k) => k.startsWith('--shadow-'))).toEqual([]);
    });

    it('keeps every colour and chip token exactly as before the restyle', () => {
        const actual: Record<string, string> = {};
        for (const [k, v] of [...themeBlockVars, ...themeTokens]) {
            if (k.startsWith('--color-') || k.startsWith('--kg-chip-')) actual[k] = v.toLowerCase();
        }

        expect(actual).toEqual(COLOUR_SNAPSHOT);
    });

    it.each(['muted', 'primary'])('%s text reaches 4.5:1 on every text surface', (name) => {
        for (const surface of ['base-100', 'base-200', 'neutral', 'primary-soft']) {
            expect(contrast(color(name), color(surface)), `${name} on ${surface}`).toBeGreaterThanOrEqual(4.5);
        }
    });

    it('error text reaches 4.5:1 on base-200 and neutral', () => {
        for (const surface of ['base-200', 'neutral']) {
            expect(contrast(color('error'), color(surface)), `error on ${surface}`).toBeGreaterThanOrEqual(4.5);
        }
    });

    it('bottom bar icons (base-100 on base-content) reach 4.5:1', () => {
        expect(contrast(color('base-100'), color('base-content'))).toBeGreaterThanOrEqual(4.5);
    });

    it('pins the old shape and font inside .document-page', () => {
        const pin = rules.find((r) => r.selector === '.document-page' && r.decls.has('--default-font-family'));

        expect(pin).toBeDefined();
        expect(pin?.decls.get('--radius-box')).toBe('1rem');
        expect(pin?.decls.get('--radius-field')).toBe('0.625rem');
        expect(pin?.decls.get('--radius-selector')).toBe('0.5rem');
        expect(pin?.decls.get('--default-font-family')?.startsWith("'Geist'")).toBe(true);
    });

    it('draws no shadow outside document rules', () => {
        const hits = outsideDocument.flatMap((r) =>
            [...r.decls]
                .filter(([prop, value]) => prop === 'box-shadow' && value !== 'none')
                .map(([prop, value]) => `${r.selector} { ${prop}: ${value} }`),
        );

        expect(hits).toEqual([]);
    });

    it('draws no border outside document, checkbox and radio rules', () => {
        const hits = outsideDocument
            .filter((r) => !/checkbox|radio/.test(r.selector))
            .flatMap((r) =>
                [...r.decls]
                    .filter(([prop, value]) => drawsBorder(prop, value))
                    .map(([prop, value]) => `${r.selector} { ${prop}: ${value} }`),
            );

        expect(hits).toEqual([]);
    });

    it('uses no gradient outside document rules', () => {
        const hits = outsideDocument.flatMap((r) =>
            [...r.decls]
                .filter(([, value]) => /(linear|radial|conic)-gradient\(/.test(value))
                .map(([prop, value]) => `${r.selector} { ${prop}: ${value} }`),
        );

        expect(hits).toEqual([]);
    });

    it("no longer frames every card (.card:not([class*='border']))", () => {
        expect(css).not.toContain(".card:not([class*='border'])");
    });
});
