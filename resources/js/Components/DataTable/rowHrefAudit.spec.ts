import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve('resources/js');

const EXEMPT: string[] = [];

function walk(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        return statSync(full).isDirectory() ? walk(full) : [full];
    });
}

function openingTags(src: string): string[] {
    const tags: string[] = [];
    let from = src.indexOf('<DataTable');
    while (from !== -1) {
        let quote: string | null = null;
        let end = from;
        for (; end < src.length; end++) {
            const char = src[end];
            if (quote) {
                if (char === quote) quote = null;
            } else if (char === '"' || char === "'") {
                quote = char;
            } else if (char === '>' && src[end - 1] !== '=') {
                break;
            }
        }
        tags.push(src.slice(from, end + 1));
        from = src.indexOf('<DataTable', end);
    }
    return tags;
}

const files = [...walk(join(root, 'Pages')), ...walk(join(root, 'Components'))]
    .filter((f) => f.endsWith('.vue'))
    .map((f) => ({ name: f.slice(root.length + 1), tags: openingTags(readFileSync(f, 'utf-8')) }))
    .filter((f) => f.tags.length > 0);

describe('every DataTable row opens its detail', () => {
    it('finds the DataTable usages', () => {
        expect(files.length).toBeGreaterThan(10);
    });

    it.each(files.map((f) => [f.name, f.tags] as const))('%s', (name, tags) => {
        if (EXEMPT.includes(name)) return;
        for (const tag of tags) expect(tag).toMatch(/:row-href=/);
    });
});
