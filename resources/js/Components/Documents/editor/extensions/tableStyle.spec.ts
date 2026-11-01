import { afterEach, describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/vue-3';
import { createDocumentExtensions } from '../documentEditorKit';

let editor: Editor | null = null;

function create(content: string): Editor {
    editor = new Editor({ extensions: createDocumentExtensions(), content });
    return editor;
}

afterEach(() => {
    editor?.destroy();
    editor = null;
});

const TABLE =
    '<table style="width: 100%; border-collapse: collapse"><tbody><tr style="height: 20px"><th style="width: 40%"><p>A</p></th><td style="padding: 4px; border: 1px solid #000"><p>B</p></td></tr></tbody></table>';

function styleOf(html: string, selector: string): string | null {
    const template = document.createElement('template');
    template.innerHTML = html;

    return template.content.querySelector(selector)?.getAttribute('style') ?? null;
}

describe('tableStyle', () => {
    it('keeps the style attribute of table, row, header and cell after parse and serialize', () => {
        const html = create(TABLE).getHTML();

        expect(styleOf(html, 'table')).toContain('width: 100%');
        expect(styleOf(html, 'tr')).toContain('height: 20px');
        expect(styleOf(html, 'th')).toContain('width: 40%');
        expect(styleOf(html, 'td')).toContain('padding: 4px');
    });

    it('keeps the style across an edit in a cell', () => {
        const e = create(TABLE);
        e.commands.setTextSelection(e.state.doc.content.size - 4);
        e.commands.insertContent('x');

        const html = e.getHTML();
        expect(html).toContain('<p>Bx</p>');
        expect(styleOf(html, 'td')).toContain('border: 1px solid #000');
        expect(styleOf(html, 'th')).toContain('width: 40%');
    });

    it('adds no style attribute to an unstyled table cell', () => {
        const html = create('<table><tbody><tr><td><p>B</p></td></tr></tbody></table>').getHTML();

        expect(styleOf(html, 'td')).toBeNull();
    });
});
