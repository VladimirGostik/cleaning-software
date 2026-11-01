import { afterEach, describe, expect, it, vi } from 'vitest';
import { Editor } from '@tiptap/vue-3';
import { createDocumentExtensions, DOCUMENT_PARSE_OPTIONS } from '../documentEditorKit';
import { canonicalSpaces, canonicalSpacesInHtml } from './wordSpaces';

let editor: Editor | undefined;

afterEach(() => editor?.destroy());

function makeEditor(content: string): Editor {
    editor = new Editor({ extensions: createDocumentExtensions(), content, parseOptions: DOCUMENT_PARSE_OPTIONS });

    return editor;
}

describe('canonicalSpaces', () => {
    it.each([
        ['a b', 'a b'],
        ['a  b', 'a  b'],
        ['a   b', 'a   b'],
        ['a    b', 'a    b'],
        ['a  b', 'a  b'],
        ['a  b', 'a  b'],
        ['a b', 'a b'],
        ['  x', '  x'],
    ])('%j -> %j', (input, expected) => {
        expect(canonicalSpaces(input)).toBe(expected);
    });

    it('leaves moustache tokens untouched', () => {
        expect(canonicalSpaces('{{  urad.nazov  }}  x')).toBe('{{  urad.nazov  }}  x');
    });

    it('is idempotent', () => {
        const once = canonicalSpaces('a       b');

        expect(canonicalSpaces(once)).toBe(once);
    });
});

describe('canonicalSpacesInHtml', () => {
    it('rewrites text segments only and writes nbsp as an entity', () => {
        expect(canonicalSpacesInHtml('<p class="a  b">x  y&nbsp;&nbsp;z</p>')).toBe(
            '<p class="a  b">x &nbsp;y &nbsp;z</p>',
        );
    });
});

describe('WordSpaces extension', () => {
    it('keeps typed runs canonical while typing', () => {
        const instance = makeEditor('<p>a</p>');
        instance.commands.focus('end');
        const typed: string[] = [];
        for (const char of [' ', ' ', 'b']) {
            instance.commands.insertContent(char);
            typed.push(instance.getText());
        }

        expect(typed).toEqual(['a ', 'a  ', 'a  b']);
        expect(instance.getHTML()).toBe('<p>a &nbsp;b</p>');
    });

    it('extends the run on the third and fourth space', () => {
        const instance = makeEditor('<p>a</p>');
        instance.commands.focus('end');

        instance.commands.insertContent('   ');
        expect(instance.getText()).toBe('a   ');
        instance.commands.insertContent(' ');
        expect(instance.getText()).toBe('a    ');
    });

    it('canonicalises pasted runs', () => {
        const instance = makeEditor('<p></p>');

        instance.commands.insertContent('x   y');

        expect(instance.getText()).toBe('x   y');
    });

    it('canonicalises content set later without emitting an update', () => {
        const instance = makeEditor('<p>a</p>');
        const onUpdate = vi.fn();
        instance.on('update', onUpdate);

        instance.commands.setContent('<p>dve  medzery</p>', {
            emitUpdate: false,
            parseOptions: DOCUMENT_PARSE_OPTIONS,
        });

        expect(instance.getText()).toBe('dve  medzery');
        expect(onUpdate).not.toHaveBeenCalled();
    });

    it('keeps the cursor after the typed space', () => {
        const instance = makeEditor('<p>a</p>');
        instance.commands.focus('end');

        instance.commands.insertContent('  ');
        instance.commands.insertContent('b');

        expect(instance.getText()).toBe('a  b');
    });

    it('does nothing for transactions that do not change the document', () => {
        const instance = makeEditor('<p>a  b</p>');
        const before = instance.state.doc;

        instance.view.dispatch(instance.state.tr.setMeta('x', true));

        expect(instance.state.doc).toBe(before);
    });

    it('keeps the cursor right after a run typed in the middle of the text', () => {
        const instance = makeEditor('<p>foobar baz</p>');
        instance.commands.setTextSelection(4);

        instance.commands.insertContent(' ');
        instance.commands.insertContent(' ');
        expect(instance.state.selection.from).toBe(6);

        instance.commands.insertContent('X');

        expect(instance.getText()).toBe('foo \u00a0Xbar baz');
    });

    it('extends a run with a third space typed inside it and keeps the cursor there', () => {
        const instance = makeEditor('<p>a \u00a0b tail</p>');
        instance.commands.setTextSelection(3);

        instance.commands.insertContent(' ');
        expect(instance.state.selection.from).toBe(4);
        instance.commands.insertContent('Y');

        expect(instance.getText()).toBe('a \u00a0Y b tail');
    });
});
