import { onBeforeUnmount, watch, type ShallowRef } from 'vue';
import { useEditor, type AnyExtension, type Editor } from '@tiptap/vue-3';
import {
    canonicalEditorHtml,
    createDocumentExtensions,
    DOCUMENT_PARSE_OPTIONS,
} from '@/Components/Documents/editor/documentEditorKit';

export interface UseDocumentEditorOptions {
    content: () => string;
    onChange: (html: string) => void;
    ariaLabel: string;
    extraExtensions?: AnyExtension[];
    textAlignTypes?: readonly string[];
    editable?: boolean;
    toEditorHtml?: (stored: string) => string;
    fromEditorHtml?: (editorHtml: string) => string;
}

const identity = (html: string): string => html;

export function useDocumentEditor(options: UseDocumentEditorOptions): {
    editor: ShallowRef<Editor | undefined>;
} {
    const toEditorHtml = options.toEditorHtml ?? identity;
    const fromEditorHtml = options.fromEditorHtml ?? identity;
    const extensions = [
        ...createDocumentExtensions({ textAlignTypes: options.textAlignTypes }),
        ...(options.extraExtensions ?? []),
    ];

    const editor = useEditor({
        content: toEditorHtml(options.content()),
        extensions,
        editable: options.editable ?? true,
        parseOptions: DOCUMENT_PARSE_OPTIONS,
        editorProps: {
            attributes: {
                class: 'document-body document-page--editing',
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': options.ariaLabel,
                ...(options.editable === false ? { 'aria-readonly': 'true' } : {}),
            },
        },
        onUpdate: ({ editor: instance }) => options.onChange(fromEditorHtml(instance.getHTML())),
    });

    watch([options.content, editor], ([incoming, instance]) => {
        if (!instance) return;
        const next = toEditorHtml(incoming);
        if (canonicalEditorHtml(next, instance.schema) === instance.getHTML()) return;
        instance.commands.setContent(next, { emitUpdate: false, parseOptions: DOCUMENT_PARSE_OPTIONS });
    });

    onBeforeUnmount(() => editor.value?.destroy());

    return { editor };
}
