import { afterEach, describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/vue-3';
import { editorToStoredHtml } from '@/Components/Documents/editor/extensions/templateTokenHtml';
import { TemplateBlockToken } from '@/Components/Documents/editor/extensions/templateToken';
import { canonicalDocumentHtml } from '@/test-utils/canonicalDocumentHtml';
import { createDocumentExtensions } from './documentEditorKit';
import {
    DOCUMENT_TOOLBAR,
    getFontFamily,
    getFontSizePt,
    getParagraphStyle,
    isParagraphStyleDisabled,
    isToolbarActionActive,
    isToolbarActionDisabled,
    runToolbarAction,
    setFontFamily,
    setFontSizePt,
    setHighlightColor,
    setParagraphStyle,
    setTextColor,
} from './documentToolbar';

let editor: Editor | null = null;

function create(content: string): Editor {
    editor = new Editor({ extensions: createDocumentExtensions(), content });

    return editor;
}

afterEach(() => {
    editor?.destroy();
    editor = null;
});

describe('DOCUMENT_TOOLBAR', () => {
    it('has the shared group layout', () => {
        expect(DOCUMENT_TOOLBAR).toEqual([
            ['undo', 'redo'],
            ['paragraphStyle'],
            ['fontFamily', 'fontSize'],
            ['bold', 'italic', 'underline', 'strike'],
            ['textColor', 'highlight'],
            ['alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
            ['bulletList', 'orderedList', 'outdent', 'indent'],
            ['horizontalRule'],
            ['clearFormatting'],
        ]);
    });
});

describe('toolbar commands', () => {
    it('produces underline and horizontal rule', () => {
        const e = create('<p>abc</p>');
        e.commands.selectAll();
        runToolbarAction(e, 'underline');
        expect(e.getHTML()).toBe('<p><u>abc</u></p>');
        expect(isToolbarActionActive(e, 'underline')).toBe(true);

        e.commands.setTextSelection(1);
        runToolbarAction(e, 'horizontalRule');
        expect(e.getHTML()).toContain('<hr>');
    });

    it('switches paragraph style keeping alignment and indent', () => {
        const e = create('<p style="margin-left: 2em; text-align: center">x</p>');
        e.commands.setTextSelection(1);

        setParagraphStyle(e, 'h2');

        expect(getParagraphStyle(e)).toBe('h2');
        expect(e.getHTML()).toBe('<h2 style="margin-left: 2em; text-align: center;">x</h2>');

        setParagraphStyle(e, 'normal');
        expect(e.getHTML()).toBe('<p style="margin-left: 2em; text-align: center;">x</p>');
    });

    it('aligns headings', () => {
        const e = create('<h2>x</h2>');
        e.commands.setTextSelection(1);

        runToolbarAction(e, 'alignCenter');

        expect(e.getHTML()).toBe('<h2 style="text-align: center;">x</h2>');
        expect(isToolbarActionActive(e, 'alignCenter')).toBe(true);
        expect(isToolbarActionActive(e, 'alignLeft')).toBe(false);
    });

    it('indents paragraphs within bounds', () => {
        const e = create('<p>x</p>');
        e.commands.setTextSelection(1);

        runToolbarAction(e, 'indent');
        runToolbarAction(e, 'indent');
        expect(e.getHTML()).toBe('<p style="margin-left: 4em;">x</p>');

        runToolbarAction(e, 'outdent');
        runToolbarAction(e, 'outdent');
        runToolbarAction(e, 'outdent');
        expect(e.getHTML()).toBe('<p>x</p>');
    });

    it('nests list items on indent', () => {
        const e = create('<ul><li><p>a</p></li><li><p>b</p></li></ul>');
        e.commands.setTextSelection(e.state.doc.content.size - 2);

        runToolbarAction(e, 'indent');

        expect(e.getHTML()).toBe('<ul><li><p>a</p><ul><li><p>b</p></li></ul></li></ul>');
    });

    it('removes the span when the default font and size are picked', () => {
        const e = create('<p>x</p>');
        e.commands.selectAll();

        setFontFamily(e, 'Times New Roman');
        setFontSizePt(e, 12);
        expect(canonicalDocumentHtml(e.getHTML())).toBe(
            '<p><span style="font-family: Times New Roman; font-size: 12pt;">x</span></p>',
        );
        expect(getFontFamily(e)).toBe('Times New Roman');
        expect(getFontSizePt(e)).toBe(12);

        setFontFamily(e, 'Geist');
        setFontSizePt(e, 10.5);
        expect(e.getHTML()).toBe('<p>x</p>');
        expect(getFontFamily(e)).toBeNull();
        expect(getFontSizePt(e)).toBeNull();
    });

    it('sets and unsets text and highlight colour as spans', () => {
        const e = create('<p>x</p>');
        e.commands.selectAll();

        setTextColor(e, '#cc0000');
        setHighlightColor(e, '#ffe599');
        expect(canonicalDocumentHtml(e.getHTML())).toBe(
            canonicalDocumentHtml('<p><span style="color: #cc0000; background-color: #ffe599;">x</span></p>'),
        );

        setTextColor(e, null);
        setHighlightColor(e, null);
        expect(e.getHTML()).toBe('<p>x</p>');
    });

    it('reports a legacy size outside the list as its pt value', () => {
        const e = create('<p><font size="7">x</font></p>');
        e.commands.setTextSelection(1);
        e.commands.selectAll();

        expect(getFontSizePt(e)).toBe(36);
    });

    it('aligns a bare list item', () => {
        const e = create('<ul><li>x</li></ul>');
        e.commands.setTextSelection(2);

        runToolbarAction(e, 'alignRight');

        expect(e.getHTML()).toBe('<ul><li style="text-align: right;"><p style="text-align: right;">x</p></li></ul>');
        expect(isToolbarActionActive(e, 'alignRight')).toBe(true);
    });

    it('reads block-level font and size from a list item and overrides them with a mark', () => {
        const e = create('<ul><li style="font-family: Times New Roman; font-size: 12pt;"><p>x</p></li></ul>');
        e.commands.setTextSelection({ from: 3, to: 4 });

        expect(getFontFamily(e)).toBe('Times New Roman');
        expect(getFontSizePt(e)).toBe(12);

        setFontFamily(e, 'Arial');
        setFontSizePt(e, 10.5);

        expect(canonicalDocumentHtml(e.getHTML())).toBe(
            '<ul><li style="font-family: Times New Roman; font-size: 12pt;"><p><span style="font-family: Arial; font-size: 10.5pt;">x</span></p></li></ul>',
        );
        expect(getFontFamily(e)).toBe('Arial');
        expect(getFontSizePt(e)).toBe(10.5);
    });

    it('clear formatting drops block-level font and colour but keeps alignment and indent', () => {
        const e = create(
            '<p style="margin-left: 2em; text-align: center; font-family: Times New Roman; color: #cc0000">x</p>',
        );
        e.commands.selectAll();

        runToolbarAction(e, 'clearFormatting');

        expect(e.getHTML()).toBe('<p style="margin-left: 2em; text-align: center;">x</p>');
    });

    it('clear formatting is one undo step covering marks and block styles', () => {
        const e = create('<p style="font-family: Times New Roman; color: #cc0000"><strong>x</strong></p>');
        e.commands.selectAll();

        runToolbarAction(e, 'clearFormatting');
        expect(e.getHTML()).toBe('<p>x</p>');

        e.commands.undo();

        expect(canonicalDocumentHtml(e.getHTML())).toBe(
            canonicalDocumentHtml('<p style="font-family: Times New Roman; color: #cc0000"><strong>x</strong></p>'),
        );
    });

    it('removing text colour also clears a block-level colour', () => {
        const e = create('<p style="color: #cc0000; font-family: Times New Roman">x</p>');
        e.commands.selectAll();

        setTextColor(e, null);

        expect(canonicalDocumentHtml(e.getHTML())).toBe('<p style="font-family: Times New Roman;">x</p>');
    });

    it('removing highlight also clears a block-level background', () => {
        const e = create('<p style="background-color: #ffe599; text-align: center">x</p>');
        e.commands.selectAll();

        setHighlightColor(e, null);

        expect(canonicalDocumentHtml(e.getHTML())).toBe('<p style="text-align: center;">x</p>');
    });

    it('keeps block-level font when the paragraph style changes', () => {
        const e = create('<p style="font-family: Times New Roman; font-size: 12pt">x</p>');
        e.commands.setTextSelection(1);

        setParagraphStyle(e, 'h1');

        expect(canonicalDocumentHtml(e.getHTML())).toBe(
            '<h1 style="font-family: Times New Roman; font-size: 12pt;">x</h1>',
        );
    });

    it('disables undo and redo from history state', () => {
        const e = create('<p>x</p>');

        expect(isToolbarActionDisabled(e, 'undo')).toBe(true);
        expect(isToolbarActionDisabled(e, 'redo')).toBe(true);
    });
});

describe('toolbar commands on a selected block token', () => {
    const block = '<p>x</p><div data-template-block="block.signatures" data-label="Podpis"></div>';

    function createBlockEditor(content: string): Editor {
        editor = new Editor({
            extensions: [...createDocumentExtensions({ textAlignTypes: ['templateBlock'] }), TemplateBlockToken],
            content,
        });
        editor.commands.setNodeSelection(editor.state.doc.firstChild!.nodeSize);

        return editor;
    }

    it('stores alignment, font family and size on the block paragraph', () => {
        const e = createBlockEditor(block);

        runToolbarAction(e, 'alignRight');
        setFontFamily(e, 'Times New Roman');
        setFontSizePt(e, 12);

        expect(editorToStoredHtml(e.getHTML())).toBe(
            '<p>x</p><p style="text-align: right; font-family: Times New Roman; font-size: 12pt;">{{block.signatures}}</p>',
        );
        expect(isToolbarActionActive(e, 'alignRight')).toBe(true);
        expect(getFontFamily(e)).toBe('Times New Roman');
        expect(getFontSizePt(e)).toBe(12);
    });

    it('removes the properties again with left alignment, default font and default size', () => {
        const e = createBlockEditor(
            '<p>x</p><div data-template-block="block.signatures" data-label="Podpis" style="text-align: right; font-family: Times New Roman; font-size: 12pt"></div>',
        );

        runToolbarAction(e, 'alignLeft');
        setFontFamily(e, 'Geist');
        setFontSizePt(e, 10.5);

        expect(editorToStoredHtml(e.getHTML())).toBe('<p>x</p><p>{{block.signatures}}</p>');
        expect(getFontFamily(e)).toBeNull();
        expect(getFontSizePt(e)).toBeNull();
        expect(isToolbarActionActive(e, 'alignLeft')).toBe(true);
    });
});

describe('list-safe paragraph style', () => {
    const FIRST_LI = '<ul><li style="font-size: 12pt"><p>a</p></li></ul>';

    it('leaves a list untouched when a heading is not allowed in its first paragraph', () => {
        const e = create(FIRST_LI);
        e.commands.setTextSelection(3);

        setParagraphStyle(e, 'h1');

        expect(e.getHTML()).toBe('<ul><li style="font-size: 12pt;"><p>a</p></li></ul>');
        expect(getParagraphStyle(e)).toBe('normal');
    });

    it('disables headings only where the schema forbids them', () => {
        const e = create(FIRST_LI);
        e.commands.setTextSelection(3);

        expect(isParagraphStyleDisabled(e, 'normal')).toBe(false);
        for (const style of ['h1', 'h2', 'h3'] as const) {
            expect(isParagraphStyleDisabled(e, style)).toBe(true);
        }

        const plain = create('<p>x</p>');
        plain.commands.setTextSelection(1);
        for (const style of ['normal', 'h1', 'h2', 'h3'] as const) {
            expect(isParagraphStyleDisabled(plain, style)).toBe(false);
        }
    });

    it('allows a heading on a further paragraph of a list item and back', () => {
        const e = create('<ul><li><p>a</p><p>b</p></li></ul>');
        e.commands.setTextSelection(8);

        expect(isParagraphStyleDisabled(e, 'h2')).toBe(false);
        setParagraphStyle(e, 'h2');
        expect(e.getHTML()).toBe('<ul><li><p>a</p><h2>b</h2></li></ul>');

        setParagraphStyle(e, 'normal');
        expect(e.getHTML()).toBe('<ul><li><p>a</p><p>b</p></li></ul>');
    });

    it('changes only the blocks that can change in a mixed selection', () => {
        const e = create('<p>x</p><ul><li><p>a</p></li></ul>');
        e.commands.selectAll();

        setParagraphStyle(e, 'h1');

        expect(e.getHTML()).toBe('<h1>x</h1><ul><li><p>a</p></li></ul>');
    });
});

describe('paragraph style on mixed blocks', () => {
    it('keeps the alignment of every block', () => {
        const e = create('<p style="text-align: center">a</p><p>b</p><p style="text-align: right">c</p>');
        e.commands.selectAll();

        setParagraphStyle(e, 'h2');

        expect(e.getHTML()).toBe(
            '<h2 style="text-align: center;">a</h2><h2>b</h2><h2 style="text-align: right;">c</h2>',
        );
    });
});
