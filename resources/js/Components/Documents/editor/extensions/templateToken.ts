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

export const TemplateToken = Node.create({
    name: 'templateToken',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes: tokenAttributes,

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

    renderHTML({ node }) {
        const kind = node.attrs.kind as DocumentTokenKind;
        return [
            'span',
            mergeAttributes({
                'data-template-token': node.attrs.key,
                'data-kind': kind,
                'data-label': node.attrs.label,
                contenteditable: 'false',
                class: `mx-0.5 ${TOKEN_CHIP_TONE[kind] ?? TOKEN_CHIP_TONE.text}`,
            }),
            node.attrs.label || node.attrs.key,
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
