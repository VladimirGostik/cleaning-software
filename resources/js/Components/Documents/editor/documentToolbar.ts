import type { CommandProps, Editor } from '@tiptap/vue-3';
import {
    DEFAULT_DOCUMENT_FONT,
    DEFAULT_DOCUMENT_FONT_SIZE_PT,
    DOCUMENT_FONTS,
    MAX_INDENT_LEVEL,
    type DocumentFont,
} from './documentEditorKit';
import { BLOCK_STYLE_TYPES } from './extensions/blockTextStyle';
import { canSetHeading, setBlockTypeKeepingParents } from './extensions/listSafeHeading';

export type DocumentToolbarItemId =
    | 'undo'
    | 'redo'
    | 'paragraphStyle'
    | 'fontFamily'
    | 'fontSize'
    | 'bold'
    | 'italic'
    | 'underline'
    | 'strike'
    | 'textColor'
    | 'highlight'
    | 'alignLeft'
    | 'alignCenter'
    | 'alignRight'
    | 'alignJustify'
    | 'bulletList'
    | 'orderedList'
    | 'outdent'
    | 'indent'
    | 'horizontalRule'
    | 'clearFormatting';

export const DOCUMENT_TOOLBAR: readonly (readonly DocumentToolbarItemId[])[] = [
    ['undo', 'redo'],
    ['paragraphStyle'],
    ['fontFamily', 'fontSize'],
    ['bold', 'italic', 'underline', 'strike'],
    ['textColor', 'highlight'],
    ['alignLeft', 'alignCenter', 'alignRight', 'alignJustify'],
    ['bulletList', 'orderedList', 'outdent', 'indent'],
    ['horizontalRule'],
    ['clearFormatting'],
];

export type DocumentToolbarActionId = Exclude<
    DocumentToolbarItemId,
    'paragraphStyle' | 'fontFamily' | 'fontSize' | 'textColor' | 'highlight'
>;

export type ParagraphStyle = 'normal' | 'h1' | 'h2' | 'h3';
export const PARAGRAPH_STYLES: readonly ParagraphStyle[] = ['normal', 'h1', 'h2', 'h3'];

const ALIGNMENTS = {
    alignLeft: 'left',
    alignCenter: 'center',
    alignRight: 'right',
    alignJustify: 'justify',
} as const;

const INLINE_MARKS_CLEARED_BY_FORMAT_RESET = [
    'bold',
    'italic',
    'underline',
    'strike',
    'textStyle',
    'highlight',
] as const;

const TOGGLE_MARKS = {
    bold: 'bold',
    italic: 'italic',
    underline: 'underline',
    strike: 'strike',
    bulletList: 'bulletList',
    orderedList: 'orderedList',
} as const;

function selectedAtom(editor: Editor, attribute: string): { name: string; attrs: Record<string, unknown> } | null {
    const node = (editor.state.selection as { node?: Editor['state']['doc'] }).node;
    if (!node || !node.type.spec.attrs || !(attribute in node.type.spec.attrs)) return null;

    return { name: node.type.name, attrs: node.attrs };
}

const BLOCK_STYLE_ATTRIBUTES = ['fontFamily', 'fontSize', 'color', 'backgroundColor'] as const;

type BlockStyleAttribute = (typeof BLOCK_STYLE_ATTRIBUTES)[number];

function blockStyleValue(editor: Editor, attribute: BlockStyleAttribute): string | null {
    for (const name of BLOCK_STYLE_TYPES) {
        const value = editor.getAttributes(name)[attribute] as string | null | undefined;
        if (value) return value;
    }

    return null;
}

function selectionHasBlockStyle(editor: Editor, attribute: BlockStyleAttribute): boolean {
    const { from, to } = editor.state.selection;
    let found = false;
    editor.state.doc.nodesBetween(from, to, (node) => {
        if (BLOCK_STYLE_TYPES.includes(node.type.name) && node.attrs[attribute]) found = true;

        return !found;
    });

    return found;
}

function blockStyleReset(attributes: readonly BlockStyleAttribute[]) {
    return ({ tr }: Pick<CommandProps, 'tr'>): boolean => {
        const { from, to } = tr.selection;
        const blank = Object.fromEntries(attributes.map((attribute) => [attribute, null]));
        tr.doc.nodesBetween(from, to, (node, pos) => {
            if (BLOCK_STYLE_TYPES.includes(node.type.name) && attributes.some((a) => node.attrs[a])) {
                tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...blank });
            }
        });

        return true;
    };
}

function shiftIndent(editor: Editor, delta: 1 | -1): void {
    editor
        .chain()
        .focus()
        .command(({ tr, state, dispatch }) => {
            let changed = false;
            state.doc.nodesBetween(state.selection.from, state.selection.to, (node, pos) => {
                if (node.type.name !== 'paragraph' && node.type.name !== 'heading') return true;
                const current = Number(node.attrs.indent ?? 0);
                const next = Math.min(MAX_INDENT_LEVEL, Math.max(0, current + delta));
                if (next !== current) {
                    tr.setNodeMarkup(pos, undefined, { ...node.attrs, indent: next });
                    changed = true;
                }

                return false;
            });
            if (changed && dispatch) dispatch(tr);

            return changed;
        })
        .run();
}

export function runToolbarAction(editor: Editor, id: DocumentToolbarActionId): void {
    const chain = editor.chain().focus();

    switch (id) {
        case 'undo':
            chain.undo().run();
            break;
        case 'redo':
            chain.redo().run();
            break;
        case 'bold':
            chain.toggleBold().run();
            break;
        case 'italic':
            chain.toggleItalic().run();
            break;
        case 'underline':
            chain.toggleUnderline().run();
            break;
        case 'strike':
            chain.toggleStrike().run();
            break;
        case 'alignLeft':
        case 'alignCenter':
        case 'alignRight':
        case 'alignJustify':
            chain.setTextAlign(ALIGNMENTS[id]).run();
            break;
        case 'bulletList':
            chain.toggleBulletList().run();
            break;
        case 'orderedList':
            chain.toggleOrderedList().run();
            break;
        case 'indent':
            if (editor.isActive('listItem')) chain.sinkListItem('listItem').run();
            else shiftIndent(editor, 1);
            break;
        case 'outdent':
            if (editor.isActive('listItem')) chain.liftListItem('listItem').run();
            else shiftIndent(editor, -1);
            break;
        case 'horizontalRule':
            chain.setHorizontalRule().run();
            break;
        case 'clearFormatting':
            INLINE_MARKS_CLEARED_BY_FORMAT_RESET.reduce((acc, mark) => acc.unsetMark(mark), chain)
                .command(blockStyleReset(BLOCK_STYLE_ATTRIBUTES))
                .run();
            break;
    }
}

function currentAlignment(editor: Editor): string | null {
    const atom = selectedAtom(editor, 'textAlign');
    if (atom) return (atom.attrs.textAlign as string | null | undefined) ?? null;

    return (
        (editor.getAttributes('heading').textAlign as string | undefined) ??
        (editor.getAttributes('paragraph').textAlign as string | undefined) ??
        (editor.getAttributes('listItem').textAlign as string | undefined) ??
        null
    );
}

export function isToolbarActionActive(editor: Editor, id: DocumentToolbarActionId): boolean {
    if (id in TOGGLE_MARKS) return editor.isActive(TOGGLE_MARKS[id as keyof typeof TOGGLE_MARKS]);
    if (id in ALIGNMENTS) {
        const alignment = currentAlignment(editor);
        const wanted = ALIGNMENTS[id as keyof typeof ALIGNMENTS];

        return alignment === wanted || (wanted === 'left' && (alignment === null || alignment === ''));
    }

    return false;
}

export function isToolbarActionDisabled(editor: Editor, id: DocumentToolbarActionId): boolean {
    if (id === 'undo') return !editor.can().undo();
    if (id === 'redo') return !editor.can().redo();

    return false;
}

export function getParagraphStyle(editor: Editor): ParagraphStyle {
    for (const level of [1, 2, 3] as const) {
        if (editor.isActive('heading', { level })) return `h${level}`;
    }

    return 'normal';
}

export function setParagraphStyle(editor: Editor, style: ParagraphStyle): void {
    const command =
        style === 'normal'
            ? setBlockTypeKeepingParents('paragraph')
            : setBlockTypeKeepingParents('heading', { level: Number(style.slice(1)) });

    editor.chain().focus().command(command).run();
}

export function isParagraphStyleDisabled(editor: Editor, style: ParagraphStyle): boolean {
    if (style === 'normal' || style === getParagraphStyle(editor)) return false;

    return !canSetHeading(editor.state);
}

export function getFontFamily(editor: Editor): DocumentFont | null {
    const atom = selectedAtom(editor, 'fontFamily');
    const value = (
        atom
            ? atom.attrs.fontFamily
            : (editor.getAttributes('textStyle').fontFamily ?? blockStyleValue(editor, 'fontFamily'))
    ) as string | null | undefined;

    return DOCUMENT_FONTS.find((font) => font.toLowerCase() === value?.toLowerCase()) ?? null;
}

export function setFontFamily(editor: Editor, font: DocumentFont): void {
    const chain = editor.chain().focus();
    const atom = selectedAtom(editor, 'fontFamily');

    if (atom) {
        chain.updateAttributes(atom.name, { fontFamily: font === DEFAULT_DOCUMENT_FONT ? null : font }).run();
        return;
    }

    const resetsToDefault = font === DEFAULT_DOCUMENT_FONT && !selectionHasBlockStyle(editor, 'fontFamily');

    if (resetsToDefault) chain.unsetFontFamily().run();
    else chain.setFontFamily(font).run();
}

export function getFontSizePt(editor: Editor): number | null {
    const atom = selectedAtom(editor, 'fontSize');
    const value = (
        atom ? atom.attrs.fontSize : (editor.getAttributes('textStyle').fontSize ?? blockStyleValue(editor, 'fontSize'))
    ) as string | null | undefined;
    const match = /^\s*(\d*\.?\d+)\s*(pt|px)?\s*$/i.exec(value ?? '');
    if (!match) return null;
    const amount = Number(match[1]);

    return match[2]?.toLowerCase() === 'px' ? Math.round(amount * 0.75 * 100) / 100 : amount;
}

export function setFontSizePt(editor: Editor, pt: number): void {
    const chain = editor.chain().focus();
    const atom = selectedAtom(editor, 'fontSize');

    if (atom) {
        chain.updateAttributes(atom.name, { fontSize: pt === DEFAULT_DOCUMENT_FONT_SIZE_PT ? null : `${pt}pt` }).run();
        return;
    }

    const resetsToDefault = pt === DEFAULT_DOCUMENT_FONT_SIZE_PT && !selectionHasBlockStyle(editor, 'fontSize');

    if (resetsToDefault) chain.unsetFontSize().run();
    else chain.setFontSize(`${pt}pt`).run();
}

export function setTextColor(editor: Editor, color: string | null): void {
    const chain = editor.chain().focus();

    if (color) chain.setColor(color).run();
    else
        chain
            .unsetColor()
            .command(blockStyleReset(['color']))
            .run();
}

export function setHighlightColor(editor: Editor, color: string | null): void {
    const chain = editor.chain().focus();

    if (color) chain.setBackgroundColor(color).run();
    else
        chain
            .unsetBackgroundColor()
            .command(blockStyleReset(['backgroundColor']))
            .run();
}
