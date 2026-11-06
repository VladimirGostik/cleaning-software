import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve('resources/js');
const locales = ['sk', 'en', 'uk'] as const;

function walk(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const full = join(dir, name);
        return statSync(full).isDirectory() ? walk(full) : [full];
    });
}

const sources = walk(root).filter((f) => /\.(vue|ts)$/.test(f) && !f.endsWith('.spec.ts'));
const keysWithParams = new Set(
    sources.flatMap((file) =>
        [...readFileSync(file, 'utf-8').matchAll(/\bt\(\s*'([\w.]+)'\s*,\s*\{/g)].map((m) => m[1] as string),
    ),
);

const messages = Object.fromEntries(
    locales.map((l) => [
        l,
        JSON.parse(readFileSync(resolve(`resources/lang/${l}/app.json`), 'utf-8')) as Record<string, string>,
    ]),
);

describe('vue-i18n placeholders', () => {
    it('finds keys called with params', () => {
        expect(keysWithParams.size).toBeGreaterThan(10);
    });

    it.each([...keysWithParams].map((k) => [k]))('%s uses {name}, not :name', (key) => {
        for (const locale of locales) {
            const message = messages[locale]?.[key];
            if (message === undefined) continue;
            expect(message, `${locale}/${key}`).not.toMatch(/:[a-z_]+/i);
        }
    });
});
