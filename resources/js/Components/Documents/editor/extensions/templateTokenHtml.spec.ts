import { describe, expect, it } from 'vitest';
import { editorToStoredHtml, storedToEditorHtml, storedToReadonlyHtml, usedTokenKeys } from './templateTokenHtml';

type DocumentTokenDef = App.Data.DocumentTemplates.DocumentTokenData;

const tokens: DocumentTokenDef[] = [
    { key: 'tenant.name', label: 'Názov firmy', kind: 'text', group: 'tenant' },
    { key: 'tenant.dic', label: 'DIČ firmy', kind: 'optional', group: 'tenant' },
    { key: 'block.signatures', label: 'Podpisy', kind: 'block', group: 'blocks' },
    { key: 'if.vat_payer', label: 'Platiteľ DPH', kind: 'flag', group: 'conditions' },
    { key: 'client.name', label: 'Tom & <b>Jerry</b>', kind: 'text', group: 'client' },
];

const defs: Record<string, DocumentTokenDef> = Object.fromEntries(tokens.map((token) => [token.key, token]));

describe('stored <-> editor html', () => {
    it.each([
        ['text', '<p>Firma {{tenant.name}} oznamuje.</p>', 'data-kind="text"'],
        ['optional', '<p>DIC {{tenant.dic}}</p>', 'data-kind="optional"'],
        ['flag', '<p>{{if.vat_payer}}Platca DPH.</p>', 'data-kind="flag"'],
        ['block', '<p>text</p><p>{{block.signatures}}</p>', '<div data-template-block="block.signatures"'],
    ])('round trip is byte-stable for %s', (_kind, stored, marker) => {
        const editor = storedToEditorHtml(stored, defs);

        expect(editor).toContain(marker);
        expect(editorToStoredHtml(editor)).toBe(stored);
    });

    it('normalizes spaced tokens to the canonical form without spaces', () => {
        const editor = storedToEditorHtml('<p>{{ tenant.name }} a {{  tenant.dic}}</p>', defs);

        expect(editorToStoredHtml(editor)).toBe('<p>{{tenant.name}} a {{tenant.dic}}</p>');
    });

    it('carries the label into the chip and escapes markup in it', () => {
        const editor = storedToEditorHtml('<p>{{client.name}}</p>', defs);

        expect(editor).toContain('data-label="Tom &amp; &lt;b&gt;Jerry&lt;/b&gt;"');
        expect(editor).not.toContain('<b>');
    });

    it('keeps an unknown token round-tripping with its key as label and prefix-derived kind', () => {
        const editor = storedToEditorHtml('<p>{{x.y}}</p><p>{{if.other}}</p>', defs);

        expect(editor).toContain('data-label="x.y"');
        expect(editor).toContain('data-template-token="if.other" data-kind="flag"');
        expect(editorToStoredHtml(editor)).toBe('<p>{{x.y}}</p><p>{{if.other}}</p>');
    });

    it('leaves a moustache inside a tag attribute and code-looking text untouched', () => {
        const stored = '<p title="{{tenant.name}}">{{ $x }} @php</p>';

        expect(storedToEditorHtml(stored, defs)).toBe(stored);
    });

    it('does not turn a block token inside a sentence into a block chip', () => {
        const editor = storedToEditorHtml('<p>a {{block.signatures}} b</p>', defs);

        expect(editor).not.toContain('data-template-block');
        expect(editorToStoredHtml(editor)).toBe('<p>a {{block.signatures}} b</p>');
    });
});

describe('block paragraph style', () => {
    const styled =
        '<p style="text-align: right; font-family: Times New Roman; font-size: 12pt;">{{block.signatures}}</p>';

    it('carries the paragraph style onto the block chip and back', () => {
        const editor = storedToEditorHtml(styled, defs);

        expect(editor).toContain('style="text-align: right; font-family: Times New Roman; font-size: 12pt;"');
        expect(editorToStoredHtml(editor)).toBe(styled);
    });

    it('keeps text-align on a block paragraph authored with a single property', () => {
        const stored = '<p style="text-align: right;">{{block.signatures}}</p>';

        expect(editorToStoredHtml(storedToEditorHtml(stored, defs))).toBe(stored);
    });

    it('writes the properties in a fixed order and strips quotes from the font family', () => {
        const editor =
            '<div data-template-block="block.signatures" data-label="Podpisy" style="font-size: 12pt; font-family: &quot;Times New Roman&quot;; text-align: center"></div>';

        expect(editorToStoredHtml(editor)).toBe(
            '<p style="text-align: center; font-family: Times New Roman; font-size: 12pt;">{{block.signatures}}</p>',
        );
    });

    it('stores no style for left alignment or an empty style', () => {
        const editor =
            '<div data-template-block="block.signatures" data-label="Podpisy" style="text-align: left"></div><div data-template-block="block.parties" data-label="X"></div>';

        expect(editorToStoredHtml(editor)).toBe('<p>{{block.signatures}}</p><p>{{block.parties}}</p>');
    });
});

describe('storedToReadonlyHtml', () => {
    it('renders chips with labels only in text segments', () => {
        const html = storedToReadonlyHtml('<p title="{{tenant.name}}">A {{tenant.name}}</p>', tokens);

        expect(html).toContain('title="{{tenant.name}}"');
        expect(html).toContain('<span class="badge badge-info" data-template-token="tenant.name">Názov firmy</span>');
    });

    it('uses the key as label for an unknown token', () => {
        expect(storedToReadonlyHtml('<p>{{x.y}}</p>', tokens)).toContain('>x.y</span>');
    });

    it('cannot produce markup from a label', () => {
        const html = storedToReadonlyHtml('<p>{{client.name}}</p>', tokens);

        expect(html).toContain('Tom &amp; &lt;b&gt;Jerry&lt;/b&gt;');
        expect(html).not.toContain('<b>');
    });

    it('renders a block token as a block chip', () => {
        expect(storedToReadonlyHtml('<p>{{block.signatures}}</p>', tokens)).toContain('class="document-block-chip"');
    });
});

describe('usedTokenKeys', () => {
    it('returns unique keys in order, tolerating whitespace variants', () => {
        const stored = '<p>{{x}} {{ tenant.name }} {{x}}</p><p>{{block.signatures}}</p><p>{{if.flag}}text</p>';

        expect(usedTokenKeys(stored)).toEqual(['x', 'tenant.name', 'block.signatures', 'if.flag']);
    });

    it('returns nothing when there are no tokens', () => {
        expect(usedTokenKeys('<p>Ahoj $x @php</p>')).toEqual([]);
    });
});
