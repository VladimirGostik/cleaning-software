import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, nextTick, ref } from 'vue';
import { useDocumentEditor } from './useDocumentEditor';

function flushPromises(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve));
}

function mountEditor(initial: string, extra: Partial<Parameters<typeof useDocumentEditor>[0]> = {}) {
    const content = ref(initial);
    const onChange = vi.fn();
    let exposed!: ReturnType<typeof useDocumentEditor>;
    const app = createApp(
        defineComponent({
            setup() {
                exposed = useDocumentEditor({
                    content: () => content.value,
                    onChange,
                    ariaLabel: 'Dokument',
                    ...extra,
                });

                return () => null;
            },
        }),
    );
    app.mount(document.createElement('div'));

    return {
        content,
        onChange,
        wrapper: app,
        get editor() {
            return exposed.editor.value!;
        },
    };
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('useDocumentEditor', () => {
    it('creates an accessible page-styled editor', async () => {
        const { editor } = mountEditor('<p>x</p>');
        await flushPromises();

        const attrs = editor.options.editorProps.attributes as Record<string, string>;
        expect(attrs.role).toBe('textbox');
        expect(attrs['aria-label']).toBe('Dokument');
        expect(attrs.class).toContain('document-page--editing');
        expect(editor.getHTML()).toBe('<p>x</p>');
    });

    it('reports user edits through onChange', async () => {
        const ctx = mountEditor('<p>x</p>');
        await flushPromises();

        ctx.editor.commands.insertContent('y');

        expect(ctx.onChange).toHaveBeenCalledWith('<p>yx</p>');
    });

    it('does not reset the editor for a sanitizer-reformatted equivalent', async () => {
        const ctx = mountEditor('<p style="text-align: center">a<br>b</p>');
        await flushPromises();
        const setContent = vi.spyOn(ctx.editor.commands, 'setContent');

        ctx.content.value = '<p style="text-align: center;">a<br />b</p>';
        await nextTick();

        expect(setContent).not.toHaveBeenCalled();
    });

    it('replaces semantically different content without emitting a change', async () => {
        const ctx = mountEditor('<p>x</p>');
        await flushPromises();

        ctx.content.value = '<p>other</p>';
        await nextTick();

        expect(ctx.editor.getHTML()).toBe('<p>other</p>');
        expect(ctx.onChange).not.toHaveBeenCalled();
    });

    it('applies the stored/editor transforms in both directions', async () => {
        const ctx = mountEditor('<p>[a]</p>', {
            toEditorHtml: (stored) => stored.replace('[a]', 'A'),
            fromEditorHtml: (html) => html.replace('A', '[a]'),
        });
        await flushPromises();
        expect(ctx.editor.getHTML()).toBe('<p>A</p>');

        ctx.editor.commands.insertContent('A');

        expect(ctx.onChange).toHaveBeenCalledWith('<p>[a]A</p>');
    });

    it('destroys the editor on unmount', async () => {
        const ctx = mountEditor('<p>x</p>');
        await flushPromises();
        const editor = ctx.editor;

        ctx.wrapper.unmount();

        expect(editor.isDestroyed).toBe(true);
    });

    it('puts the page geometry on an outer sheet, not on the editing root', async () => {
        const { editor } = mountEditor('<p>x</p>');
        await flushPromises();

        const attrs = editor.options.editorProps.attributes as Record<string, string>;
        expect(attrs.class).toContain('document-body');
        expect(attrs.class).not.toContain('document-page ');
    });

    it('keeps typed runs of spaces and does not reset the editor on the echoed content', async () => {
        const ctx = mountEditor('<p>a</p>');
        await flushPromises();
        ctx.editor.commands.focus('end');
        const setContent = vi.spyOn(ctx.editor.commands, 'setContent');

        for (const key of [' ', ' ', 'b']) {
            ctx.editor.commands.insertContent(key);
            ctx.content.value = ctx.onChange.mock.calls.at(-1)![0] as string;
            await nextTick();
        }

        expect(ctx.editor.getHTML()).toBe('<p>a &nbsp;b</p>');
        expect(ctx.onChange.mock.calls.at(-1)![0]).toBe('<p>a &nbsp;b</p>');
        expect(setContent).not.toHaveBeenCalled();
    });

    it('keeps a leading plain space and a run from an unsanitised base file on load', async () => {
        const ctx = mountEditor('<p>  x</p><p>dve  medzery</p>');
        await flushPromises();

        expect(ctx.editor.getText({ blockSeparator: '|' })).toBe(' \u00a0x|dve \u00a0medzery');
        expect(ctx.editor.getHTML()).toBe('<p> &nbsp;x</p><p>dve &nbsp;medzery</p>');
    });

    it('keeps loaded empty paragraphs', async () => {
        const ctx = mountEditor('<p>a</p><p></p><p style="text-align: right"></p>');
        await flushPromises();

        expect(ctx.editor.getHTML()).toBe('<p>a</p><p></p><p style="text-align: right;"></p>');
    });

    it('does not reset the editor when the model echoes a run written by the sanitizer', async () => {
        const ctx = mountEditor('<p>a &nbsp;b</p>');
        await flushPromises();
        const setContent = vi.spyOn(ctx.editor.commands, 'setContent');

        ctx.content.value = '<p>a \u00a0b</p>';
        await nextTick();

        expect(setContent).not.toHaveBeenCalled();
    });

    it('can be read-only', async () => {
        const { editor } = mountEditor('<p>x</p>', { editable: false });
        await flushPromises();

        expect(editor.isEditable).toBe(false);
        expect((editor.options.editorProps.attributes as Record<string, string>)['aria-readonly']).toBe('true');
    });
});
