import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve('resources/js');

const OUT_OF_SCOPE = [
    'Components/Documents/DocumentSheet.vue',
    'Components/Documents/DocumentFrame.vue',
    'Components/Documents/editor/extensions/',
];

function walk(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        return statSync(full).isDirectory() ? walk(full) : [full];
    });
}

const matchesEntry = (rel: string, entry: string) => (entry.endsWith('/') ? rel.startsWith(entry) : rel === entry);
const listed = (rel: string, list: string[]) => list.some((entry) => matchesEntry(rel, entry));

const files = walk(root)
    .filter((f) => /\.(vue|ts)$/.test(f) && !f.endsWith('.spec.ts') && !f.endsWith('.d.ts'))
    .map((f) => f.slice(root.length + 1))
    .filter((rel) => !listed(rel, OUT_OF_SCOPE))
    .sort();

const read = (path: string) => (existsSync(path) ? readFileSync(path, 'utf-8') : '');

const BANNED_CLASSES: [string, RegExp][] = [
    ['shadow', /^shadow(-.+)?$/],
    ['border', /^border(-(?!collapse$|separate$|spacing-|radius$|(start|end)-(start|end)-radius$).+)?$/],
    ['divide', /^divide-.+$/],
    ['ring', /^ring(-.+)?$/],
    ['gradient', /^bg-(gradient|linear|radial|conic)-.+$/],
    ['heavy weight', /^font-(semibold|bold|extrabold|black)$/],
    ['light weight', /^font-(thin|extralight|light)$/],
    ['rounded', /^rounded(-(sm|md|lg|xl|2xl|3xl|none|\[(?!inherit\]).+\]))?$/],
    ['opacity text', /^text-base-content\/\d+$/],
    ['pixel text', /^text-\[\d.*\]$/],
    ['btn-xs', /^btn-xs$/],
    ['field size', /^(input|select|textarea)-(xs|sm|bordered)$/],
    ['card border', /^card-border(ed)?$/],
    ['table variant', /^table-(zebra|xs|sm)$/],
];

const TOKEN_SPLIT = /[^A-Za-z0-9_\-[\]/.:%()]+/;

const tokensOf = (text: string): string[] =>
    text
        .split(TOKEN_SPLIT)
        .map((t) => t.slice(t.lastIndexOf(':') + 1))
        .filter(Boolean);

function classSource(src: string): string {
    return src
        .replace(/<style[\s\S]*?<\/style>/g, '')
        .split('\n')
        .filter((line) => !line.includes('~icons/'))
        .join('\n');
}

const ARBITRARY_COLOR =
    /(?<![\w-])(?:[a-z-]+:)*(?:bg|text|border|fill|stroke|outline|ring|from|to|via|decoration|accent|caret|shadow)-\[(?:#|rgba?\(|hsla?\(|oklch\(|color:)[^\]]*\]/g;

function arbitraryColorViolations(src: string): string[] {
    return [...classSource(src).matchAll(ARBITRARY_COLOR)].map((m) => `arbitrary colour: ${m[0]}`);
}

function templatePart(src: string): string {
    return src.replace(/<script[\s\S]*?<\/script>/g, '').replace(/<style[\s\S]*?<\/style>/g, '');
}

interface IconSet {
    icons: Record<string, unknown>;
    aliases?: Record<string, unknown>;
}

const iconSet = JSON.parse(read('node_modules/@iconify-json/material-symbols/icons.json')) as IconSet;
const iconExists = (id: string) => id in iconSet.icons || id in (iconSet.aliases ?? {});

function iconViolations(src: string): string[] {
    const out: string[] = [];
    for (const m of src.matchAll(/~icons\/([\w-]+)\/([\w-]+)/g)) {
        const [, set, id] = m;
        if (set !== 'material-symbols') {
            out.push(`icon set ${set}/${id}`);
            continue;
        }
        if (id.includes('outline')) continue;
        const twins = [`${id.replace(/-rounded$/, '')}-outline-rounded`, `${id}-outline`];
        const twin = twins.find(iconExists);
        if (twin) out.push(`filled icon ${id}, use ${twin}`);
    }
    return out;
}

function iconSizeViolations(src: string): string[] {
    const out: string[] = [];
    for (const m of src.matchAll(/<(?:[A-Z]\w*Icon\b|KG_ICONS\.[\w.]+)((?:[^>"']|"[^"]*"|'[^']*')*)>/g)) {
        for (const token of tokensOf(m[1])) {
            const size = /^(?:size|h|w)-(.+)$/.exec(token);
            if (size && !['4.5', '5', '5.5'].includes(size[1])) out.push(`icon size ${token}`);
        }
    }
    return out;
}

function ctaViolations(src: string): string[] {
    const strings = [
        ...src.matchAll(/class\s*=\s*"([^"]*)"/g),
        ...src.matchAll(/'([^'\n]*)'/g),
        ...src.matchAll(/"([^"\n]*)"/g),
        ...src.matchAll(/`([^`]*)`/g),
    ].map((m) => m[1]);
    const hit = strings.some((s) => /\bbtn-secondary\b/.test(s) && /\bbtn-sm\b/.test(s));
    return hit ? ['btn-secondary together with btn-sm'] : [];
}

const STANDALONE_TIMES = /(?<=>)\s*×\s*(?=<)|(['"`])\s*×\s*\1/;

function glyphIcon(text: string): string | undefined {
    const glyph = /\p{Extended_Pictographic}|[ⓘ↶↷⇤⇥]/u.exec(text);
    if (glyph) return glyph[0];
    return STANDALONE_TIMES.test(text) ? '×' : undefined;
}

function templateViolations(src: string): string[] {
    const out: string[] = [];
    const template = templatePart(src);
    for (const m of template.matchAll(/<(div|span)\b((?:[^>"']|"[^"]*"|'[^']*')*)>/g)) {
        if (/\brole="button"/.test(m[2])) out.push(`${m[1]} role="button"`);
    }
    const glyph = glyphIcon(template);
    if (glyph) out.push(`emoji or glyph icon ${glyph}`);
    return out;
}

function auditFile(rel: string): string[] {
    const src = readFileSync(join(root, rel), 'utf-8');
    const classes = new Set(tokensOf(classSource(src)));
    const out: string[] = [];
    for (const [rule, pattern] of BANNED_CLASSES) {
        for (const token of classes) if (pattern.test(token)) out.push(`${rule}: ${token}`);
    }
    out.push(
        ...arbitraryColorViolations(src),
        ...ctaViolations(src),
        ...iconViolations(src),
        ...iconSizeViolations(src),
        ...templateViolations(src),
    );
    return out;
}

describe('shape audit', () => {
    it.each(files)('%s', (rel) => {
        expect(auditFile(rel)).toEqual([]);
    });
});

describe('glyph icon rule', () => {
    it('reports a lone multiplication sign used as an icon', () => {
        expect(glyphIcon('<button>×</button>')).toBe('×');
        expect(glyphIcon('<span> × </span>')).toBe('×');
        expect(glyphIcon("label: '×'")).toBe('×');
    });

    it('ignores a multiplication sign inside text', () => {
        expect(glyphIcon('`${quantity}× ${adverb}`')).toBeUndefined();
        expect(glyphIcon('`${quantity}×`')).toBeUndefined();
        expect(glyphIcon('<p>2× per week</p>')).toBeUndefined();
    });

    it('still reports the other glyphs anywhere', () => {
        expect(glyphIcon('<p>ⓘ info</p>')).toBe('ⓘ');
    });
});

describe('shell layout', () => {
    it('AppLayout uses the sidebar token, the bottom bar and mobileNavItems', () => {
        const src = read(join(root, 'Layouts/AppLayout.vue'));

        expect(src).toContain('w-(--kg-sidebar-width)');
        expect(src).toContain('kg-bottom-nav');
        expect(src).toContain('mobileNavItems(');
        expect(src).not.toContain('w-64');
        expect(src).not.toContain('border-r');
    });

    it('Header title is 24 px regular', () => {
        const src = read(join(root, 'Layouts/Header.vue'));

        expect(src).toContain('text-2xl');
        expect(src).toContain('font-normal');
    });

    it('PageMainAction owns the single btn-secondary and the main-action class', () => {
        const src = read(join(root, 'Components/PageMainAction.vue'));

        expect(src.match(/btn-secondary/g) ?? []).toHaveLength(1);
        expect(src).toContain('kg-main-action');
    });

    it.each(['vite.config.js', 'vitest.config.ts'])('%s sets the default icon class', (config) => {
        expect(read(resolve(config))).toContain("defaultClass: 'kg-icon'");
    });
});
