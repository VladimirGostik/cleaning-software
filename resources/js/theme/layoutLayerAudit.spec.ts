import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const css = readFileSync('resources/css/app.css', 'utf-8').replace(/\/\*[\s\S]*?\*\//g, '');

const LAYOUT_CLASSES = ['.kg-list', '.kg-list-row', '.kg-tile'];

function unlayeredLayoutDisplayRules(): string[] {
    const found: string[] = [];
    let depth = 0;
    let layerDepth: number | null = null;
    let buffer = '';
    for (const ch of css) {
        if (ch === '{') {
            const head = buffer.trim();
            if (layerDepth === null && /^@layer\b/.test(head)) layerDepth = depth;
            depth += 1;
            if (layerDepth === null && LAYOUT_CLASSES.includes(head)) found.push(head);
            buffer = '';
        } else if (ch === '}') {
            depth -= 1;
            if (layerDepth !== null && depth === layerDepth) layerDepth = null;
            buffer = '';
        } else if (ch === ';') {
            buffer = '';
        } else {
            buffer += ch;
        }
    }
    return found;
}

describe('layout layer audit', () => {
    it('keeps kg-list, kg-list-row and kg-tile in @layer so display utilities (md:hidden) override them', () => {
        expect(unlayeredLayoutDisplayRules()).toEqual([]);
    });

    it('does not combine kg-list* with a responsive hide utility in DataTable', () => {
        const src = readFileSync('resources/js/Components/DataTable/DataTable.vue', 'utf-8');
        expect(src).toMatch(/class="kg-list md:hidden"/);
    });
});
