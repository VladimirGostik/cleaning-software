import { describe, expect, it } from 'vitest';
import { createDocument, Editor, getSchema } from '@tiptap/vue-3';
import { canonicalDocumentHtml } from '@/test-utils/canonicalDocumentHtml';
import { CONTRACT_EXPECTED_HTML as contractExpected, CONTRACT_INPUT_HTML as contractInput } from './documentFixtures';
import { canonicalEditorHtml, createDocumentExtensions } from './documentEditorKit';

const extensions = createDocumentExtensions();
const kit = (html: string): string => canonicalEditorHtml(html, extensions);

describe('document editor kit', () => {
    it('keeps every construct of the expected contract fixture', () => {
        expect(canonicalDocumentHtml(kit(contractExpected))).toBe(canonicalDocumentHtml(contractExpected));
    });

    it('normalises the raw input fixture to the expected fixture', () => {
        expect(canonicalDocumentHtml(kit(contractInput))).toBe(canonicalDocumentHtml(contractExpected));
    });

    it.each([
        ['input', contractInput],
        ['expected', contractExpected],
    ])('is a fixed point on the %s fixture', (_, fixture) => {
        expect(kit(kit(fixture))).toBe(kit(fixture));
    });

    it('keeps alignment and indent on headings', () => {
        expect(kit('<h2 style="margin-left: 2em; text-align: center">x</h2>')).toBe(
            '<h2 style="margin-left: 2em; text-align: center;">x</h2>',
        );
    });

    it('keeps authored colours in the model and serialises them as valid css colours', () => {
        const source = '<p><span style="color: #cc0000; background-color: #ffe599">x</span></p>';
        const html = kit(source);

        expect(html).toMatch(/[^-]color: (rgb\(204, 0, 0\)|#cc0000)/);
        expect(html).toMatch(/background-color: (rgb\(255, 229, 153\)|#ffe599)/);

        const doc = createDocument(source, getSchema(extensions));
        const attrs = doc.firstChild!.firstChild!.marks[0]!.attrs;
        expect(attrs.color).toBe('#cc0000');
        expect(attrs.backgroundColor).toBe('#ffe599');
    });

    it('keeps authored colours under canonicalisation', () => {
        expect(canonicalDocumentHtml(kit('<p><span style="color: #cc0000">x</span></p>'))).toBe(
            canonicalDocumentHtml('<p><span style="color: #cc0000;">x</span></p>'),
        );
    });

    it('converts legacy font tags to text style spans', () => {
        expect(
            canonicalDocumentHtml(kit('<p><font face="Times New Roman" size="5" color="#cc0000">x</font></p>')),
        ).toBe(
            canonicalDocumentHtml(
                '<p><span style="font-family: Times New Roman; font-size: 18pt; color: #cc0000;">x</span></p>',
            ),
        );
    });

    it.each([
        [1, 8],
        [2, 10],
        [3, 12],
        [4, 14],
        [5, 18],
        [6, 24],
        [7, 36],
    ])('maps legacy font size %i to %ipt', (size, pt) => {
        expect(kit(`<p><font size="${size}">x</font></p>`)).toBe(`<p><span style="font-size: ${pt}pt;">x</span></p>`);
    });

    it('converts inline-only divs to paragraphs and keeps their alignment', () => {
        expect(kit('<div style="text-align: center">x</div>')).toBe('<p style="text-align: center;">x</p>');
        expect(kit('<div>a<br>b</div>')).toBe('<p>a<br>b</p>');
    });

    it('does not append a trailing empty paragraph', () => {
        expect(kit('<h1>x</h1>')).toBe('<h1>x</h1>');
    });

    it('keeps a typed run of spaces in the Word alternating form, including a leading plain space', () => {
        expect(kit('<p>dve  medzery a tri   medzery</p>')).toBe('<p>dve &nbsp;medzery a tri &nbsp; medzery</p>');
        expect(kit('<p>  x</p>')).toBe('<p> &nbsp;x</p>');
        expect(kit('<p>a&nbsp;&nbsp;b</p>')).toBe('<p>a &nbsp;b</p>');
    });

    it('does not rewrite a lone non-breaking space or the inside of a token', () => {
        expect(kit('<p>a&nbsp;b</p>')).toBe('<p>a&nbsp;b</p>');
        expect(kit('<p>{{  urad.nazov  }}</p>')).toBe('<p>{{  urad.nazov  }}</p>');
    });

    it('keeps empty paragraphs and their alignment', () => {
        expect(kit('<p></p>')).toBe('<p></p>');
        expect(kit('<p style="text-align: right"></p>')).toBe('<p style="text-align: right;"></p>');
    });

    it('ignores the newline between block elements of a base file', () => {
        expect(kit('<p>a</p>\n<p>b</p>\n')).toBe('<p>a</p><p>b</p>');
    });

    it('keeps block-level alignment, font and colours on list items, paragraphs and headings', () => {
        const li = '<ul><li style="text-align: right; font-family: Times New Roman; font-size: 12pt;">x</li></ul>';
        const p =
            '<p style="font-family: Times New Roman; font-size: 12pt; color: #cc0000; background-color: #ffe599">x</p>';
        const h = '<h2 style="font-family: Courier New">x</h2>';

        expect(canonicalDocumentHtml(kit(li))).toBe(
            '<ul><li style="text-align: right; font-family: Times New Roman; font-size: 12pt;"><p>x</p></li></ul>',
        );
        expect(canonicalDocumentHtml(kit(p))).toBe(canonicalDocumentHtml(p));
        expect(canonicalDocumentHtml(kit(h))).toBe('<h2 style="font-family: Courier New;">x</h2>');

        for (const html of [li, p, h]) {
            expect(kit(kit(html))).toBe(kit(html));
        }
    });

    it('aligns headings, paragraphs and list items unless more types are asked for', () => {
        const names = (types?: readonly string[]) =>
            createDocumentExtensions({ textAlignTypes: types })
                .filter((extension) => extension.name === 'textAlign')
                .map((extension) => extension.options.types);

        expect(names()).toEqual([['heading', 'paragraph', 'listItem']]);
        expect(names(['templateBlock'])).toEqual([['heading', 'paragraph', 'listItem', 'templateBlock']]);
    });
});

describe('heading shortcuts', () => {
    function create(content: string): Editor {
        return new Editor({ extensions: createDocumentExtensions(), content });
    }

    it('does not lift the first paragraph of a list item out of its list', () => {
        const e = create('<ul><li><p>a</p></li></ul>');
        e.commands.setTextSelection(3);

        e.commands.keyboardShortcut('Mod-Alt-1');

        expect(e.getHTML()).toBe('<ul><li><p>a</p></li></ul>');
        e.destroy();
    });

    it('toggles a heading on a plain paragraph', () => {
        const e = create('<p>x</p>');
        e.commands.setTextSelection(1);

        e.commands.keyboardShortcut('Mod-Alt-1');
        expect(e.getHTML()).toBe('<h1>x</h1>');

        e.commands.keyboardShortcut('Mod-Alt-1');
        expect(e.getHTML()).toBe('<p>x</p>');
        e.destroy();
    });
});

describe('heading shortcut block attributes', () => {
    const STYLED = 'text-align: center; margin-left: 2em; font-family: Times New Roman';
    const KEPT = [/text-align: center/, /margin-left: 2em/, /font-family: .*Times New Roman/];

    it('keeps alignment, indent and font on the heading and on the toggle back', () => {
        const e = new Editor({ extensions: createDocumentExtensions(), content: `<p style="${STYLED}">abc</p>` });
        e.commands.setTextSelection(1);

        e.commands.keyboardShortcut('Mod-Alt-1');
        expect(e.getHTML()).toMatch(/^<h1 /);
        KEPT.forEach((pattern) => expect(e.getHTML()).toMatch(pattern));

        e.commands.keyboardShortcut('Mod-Alt-1');
        expect(e.getHTML()).toMatch(/^<p /);
        KEPT.forEach((pattern) => expect(e.getHTML()).toMatch(pattern));
        e.destroy();
    });
});
