import { mergeAttributes, Node } from '@tiptap/vue-3';
import { TextStyle } from '@tiptap/extension-text-style';

export const LEGACY_FONT_SIZES_PT = [8, 10, 12, 14, 18, 24, 36] as const;

const INLINE_TAGS = new Set(['A', 'B', 'BR', 'EM', 'FONT', 'I', 'MARK', 'S', 'SPAN', 'STRIKE', 'STRONG', 'U']);

function isInlineOnly(element: HTMLElement): boolean {
    return Array.from(element.childNodes).every(
        (child) => child.nodeType === 3 || (child.nodeType === 1 && INLINE_TAGS.has(child.nodeName)),
    );
}

export const LegacyTextStyle = TextStyle.extend({
    parseHTML() {
        return [
            ...(this.parent?.() ?? []),
            {
                tag: 'font',
                getAttrs: (node: HTMLElement | string) => {
                    if (typeof node === 'string') return false;
                    const attrs: Record<string, string> = {};
                    const face = node.getAttribute('face');
                    const size = Number(node.getAttribute('size'));
                    const color = node.getAttribute('color');
                    if (face) attrs.fontFamily = face.replace(/['"]+/g, '').trim();
                    if (Number.isInteger(size) && size >= 1 && size <= 7) {
                        attrs.fontSize = `${LEGACY_FONT_SIZES_PT[size - 1]}pt`;
                    }
                    if (color) attrs.color = color.trim();

                    return Object.keys(attrs).length > 0 ? attrs : false;
                },
            },
        ];
    },
});

export const DocumentParagraph = Node.create({
    name: 'paragraph',
    priority: 1000,
    group: 'block',
    content: 'inline*',

    parseHTML() {
        return [
            { tag: 'p' },
            {
                tag: 'div',
                priority: 40,
                getAttrs: (node: HTMLElement | string) => (typeof node !== 'string' && isInlineOnly(node) ? {} : false),
            },
        ];
    },

    renderHTML({ HTMLAttributes }) {
        return ['p', mergeAttributes(HTMLAttributes), 0];
    },
});
