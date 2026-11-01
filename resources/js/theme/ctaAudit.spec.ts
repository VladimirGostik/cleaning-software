import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve('resources/js');

function walk(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        return statSync(full).isDirectory() ? walk(full) : [full];
    });
}

const count = (src: string) => (src.match(/\bbtn-secondary\b/g) ?? []).length;

function importedComponents(src: string): string[] {
    return [...src.matchAll(/from\s+'@\/(Components\/[^']+\.vue)'/g)].map((m) => join(root, m[1]));
}

const pages = walk(join(root, 'Pages')).filter((f) => f.endsWith('.vue'));

describe('one orange CTA per screen', () => {
    it.each(pages.map((p) => [p.slice(root.length + 1), p]))('%s', (_name, file) => {
        const src = readFileSync(file, 'utf-8');
        const emptyStateCtas = (src.match(/<template[^>]*#cta[\s\S]*?<\/template>/g) ?? []).reduce(
            (n, block) => n + count(block),
            0,
        );
        const own = count(src) - emptyStateCtas;
        const nested = importedComponents(src).reduce((n, c) => {
            try {
                return n + count(readFileSync(c, 'utf-8'));
            } catch {
                return n;
            }
        }, 0);
        expect(own + nested).toBeLessThanOrEqual(1);
    });

    it('no btn-secondary uses white text', () => {
        const css = readFileSync('resources/css/app.css', 'utf-8');
        expect(css).toMatch(/--color-secondary-content:\s*#111C18/i);
    });
});
