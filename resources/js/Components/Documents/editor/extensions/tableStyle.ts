import { Extension } from '@tiptap/vue-3';

export const TABLE_STYLE_TYPES = ['table', 'tableRow', 'tableCell', 'tableHeader'];

export const TableStyle = Extension.create({
    name: 'tableStyle',

    addGlobalAttributes() {
        return [
            {
                types: TABLE_STYLE_TYPES,
                attributes: {
                    rawStyle: {
                        default: null,
                        parseHTML: (element: HTMLElement) => element.getAttribute('style') || null,
                        renderHTML: (attributes: Record<string, string | null>) =>
                            attributes.rawStyle ? { style: attributes.rawStyle } : {},
                    },
                },
            },
        ];
    },
});
