import { afterEach, describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { TemplateBlockToken, TemplateToken } from './templateToken';

let editor: Editor | null = null;

function create(content: string): Editor {
    editor = new Editor({
        extensions: [StarterKit, TextAlign.configure({ types: ['templateBlock'] }), TemplateToken, TemplateBlockToken],
        content,
    });
    return editor;
}

afterEach(() => {
    editor?.destroy();
    editor = null;
});

describe('TemplateToken', () => {
    it('parses an inline chip into an atom node with attributes', () => {
        const e = create(
            '<p>A <span data-template-token="tenant.dic" data-label="Názov" data-kind="optional"></span></p>',
        );

        const node = e.state.doc.firstChild!.lastChild!;
        expect(node.type.name).toBe('templateToken');
        expect(node.attrs).toMatchObject({ key: 'tenant.dic', label: 'Názov', kind: 'optional' });
        expect(node.isAtom).toBe(true);
    });

    it('renders a non-editable chip with the label and kind tone', () => {
        const e = create(
            '<p><span data-template-token="if.vat_payer" data-label="Príznak" data-kind="flag"></span></p>',
        );

        const chip = e.view.dom.querySelector('[data-template-token="if.vat_payer"]')!;
        expect(chip.textContent).toBe('Príznak');
        expect(chip.getAttribute('contenteditable')).toBe('false');
        expect(chip.className).toContain('kg-chip--warning');
    });

    it('falls back to the key when the label is empty and to the text tone for unknown kinds', () => {
        const e = create('<p><span data-template-token="a.b" data-kind="zzz"></span></p>');

        const chip = e.view.dom.querySelector('[data-template-token="a.b"]')!;
        expect(chip.textContent).toBe('a.b');
        expect(chip.className).toContain('kg-chip--planned');
    });
});

describe('TemplateBlockToken', () => {
    it('parses a block div as a block atom with kind block', () => {
        const e = create('<div data-template-block="block.signatures" data-label="Podpis"></div>');

        const node = e.state.doc.firstChild!;
        expect(node.type.name).toBe('templateBlock');
        expect(node.attrs).toMatchObject({ key: 'block.signatures', label: 'Podpis', kind: 'block' });
    });

    it('renders a non-editable block with the label', () => {
        const e = create('<div data-template-block="block.signatures" data-label="Podpis"></div>');

        const block = e.view.dom.querySelector('[data-template-block="block.signatures"]')!;
        expect(block.textContent).toBe('Podpis');
        expect(block.getAttribute('contenteditable')).toBe('false');
    });

    it('parses alignment, font family and size from the chip style', () => {
        const e = create(
            '<div data-template-block="block.signatures" data-label="Podpis" style="text-align: right; font-family: Times New Roman; font-size: 12pt"></div>',
        );

        expect(e.state.doc.firstChild!.attrs).toMatchObject({
            textAlign: 'right',
            fontFamily: 'Times New Roman',
            fontSize: '12pt',
        });
    });

    it('renders its own style so the chip visibly aligns and changes font', () => {
        const e = create(
            '<div data-template-block="block.signatures" data-label="Podpis" style="text-align: right; font-family: Times New Roman; font-size: 12pt"></div>',
        );

        const block = e.view.dom.querySelector<HTMLElement>('[data-template-block="block.signatures"]')!;
        expect(block.style.textAlign).toBe('right');
        expect(block.style.fontFamily).toContain('Times New Roman');
        expect(block.style.fontSize).toBe('12pt');
        expect(block.classList.contains('document-block-chip')).toBe(true);
    });

    it('has no style attributes by default', () => {
        const e = create('<div data-template-block="block.signatures" data-label="Podpis"></div>');

        expect(e.state.doc.firstChild!.attrs).toMatchObject({ fontFamily: null, fontSize: null });
        expect(e.view.dom.querySelector('[data-template-block]')!.getAttribute('style')).toBeNull();
    });
});
