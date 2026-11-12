import { mergeAttributes, Node } from '@tiptap/vue-3';
import { rawStyleAttribute } from '@/Components/Documents/editor/documentEditorKit';
import { TOKEN_CHIP_TONE } from './templateTokenHtml';

type DocumentTokenKind = App.Enums.DocumentTokenKindEnum;

function tokenAttributes() {
    return {
        key: { default: '', rendered: false },
        label: { default: '', rendered: false },
        kind: { default: 'text', rendered: false },
    };
}

function fingerprint(text: string): string {
    let hash = 5381;
    for (let index = 0; index < text.length; index++) {
        hash = ((hash << 5) + hash + text.charCodeAt(index)) | 0;
    }

    return (hash >>> 0).toString(36);
}

function previewAttribute(attribute: string, hashed: boolean) {
    return {
        default: '',
        parseHTML: (el: HTMLElement) => el.getAttribute(attribute) ?? '',
        renderHTML: hashed
            ? (attrs: Record<string, unknown>) =>
                  attrs.preview ? { 'data-preview-hash': fingerprint(String(attrs.preview)) } : {}
            : () => ({}),
    };
}

export const TemplateToken = Node.create({
    name: 'templateToken',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes() {
        return { ...tokenAttributes(), value: previewAttribute('data-value', false) };
    },

    parseHTML() {
        return [
            {
                tag: 'span[data-template-token]',
                getAttrs: (el) => {
                    const node = el as HTMLElement;
                    return {
                        key: node.getAttribute('data-template-token') ?? '',
                        label: node.getAttribute('data-label') ?? '',
                        kind: node.getAttribute('data-kind') ?? 'text',
                    };
                },
            },
        ];
    },

    renderHTML({ node, HTMLAttributes }) {
        const kind = node.attrs.kind as DocumentTokenKind;
        const value = node.attrs.value as string;
        return [
            'span',
            mergeAttributes(HTMLAttributes, {
                'data-template-token': node.attrs.key,
                'data-kind': kind,
                'data-label': node.attrs.label,
                contenteditable: 'false',
                class: value ? 'document-value-chip' : `mx-0.5 ${TOKEN_CHIP_TONE[kind] ?? TOKEN_CHIP_TONE.text}`,
            }),
            value || node.attrs.label || node.attrs.key,
        ];
    },
});

export const TemplateBlockToken = Node.create({
    name: 'templateBlock',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,

    addAttributes() {
        return {
            ...tokenAttributes(),
            preview: previewAttribute('data-preview', true),
            fontFamily: rawStyleAttribute('fontFamily', 'font-family', true),
            fontSize: rawStyleAttribute('fontSize', 'font-size'),
        };
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-template-block]',
                getAttrs: (el) => {
                    const node = el as HTMLElement;
                    return {
                        key: node.getAttribute('data-template-block') ?? '',
                        label: node.getAttribute('data-label') ?? '',
                        kind: 'block',
                    };
                },
            },
        ];
    },

    addNodeView() {
        return ({ node, HTMLAttributes }) => {
            const html = node.attrs.preview as string;
            const dom = document.createElement('div');
            for (const [name, value] of Object.entries(HTMLAttributes)) {
                if (name !== 'data-preview-hash') dom.setAttribute(name, String(value));
            }
            dom.setAttribute('data-template-block', node.attrs.key);
            dom.setAttribute('data-label', node.attrs.label);
            dom.setAttribute('contenteditable', 'false');
            dom.className = html ? 'document-block-preview' : TOKEN_CHIP_TONE.block;
            if (html) dom.innerHTML = html;
            else dom.textContent = node.attrs.label || node.attrs.key;

            return {
                dom,
                selectNode: () => dom.classList.add('ProseMirror-selectednode'),
                deselectNode: () => dom.classList.remove('ProseMirror-selectednode'),
            };
        };
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-template-block': node.attrs.key,
                'data-label': node.attrs.label,
                contenteditable: 'false',
                class: TOKEN_CHIP_TONE.block,
            }),
            node.attrs.label || node.attrs.key,
        ];
    },
});
