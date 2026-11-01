import { createDocument, getHTMLFromFragment, getSchema, type AnyExtension } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import { TableKit } from '@tiptap/extension-table';
import { BackgroundColor, Color, FontFamily, FontSize } from '@tiptap/extension-text-style';
import { Indent, MAX_INDENT_LEVEL } from './extensions/indent';
import { BlockTextStyle, BLOCK_STYLE_TYPES } from './extensions/blockTextStyle';
import { rawStyleAttribute } from './extensions/rawStyle';
import { TableStyle } from './extensions/tableStyle';
import { canonicalSpacesInHtml, WordSpaces } from './extensions/wordSpaces';
import { DocumentParagraph, LegacyTextStyle } from './extensions/legacyFormatting';
import { ListSafeHeading } from './extensions/listSafeHeading';

export { MAX_INDENT_LEVEL, rawStyleAttribute };

export const DOCUMENT_PARSE_OPTIONS = { preserveWhitespace: true } as const;

export const DOCUMENT_FONTS = ['Arial', 'Times New Roman', 'Courier New'] as const;
export type DocumentFont = (typeof DOCUMENT_FONTS)[number];
export const DEFAULT_DOCUMENT_FONT: DocumentFont = 'Arial';
export const DOCUMENT_FONT_SIZES_PT = [8, 9, 10, 10.5, 11, 12, 14, 16, 18, 24] as const;
export const DEFAULT_DOCUMENT_FONT_SIZE_PT = 10.5;
export const DOCUMENT_COLORS: readonly string[] = [
    '#000000',
    '#434343',
    '#666666',
    '#999999',
    '#cc0000',
    '#e69138',
    '#f1c232',
    '#38761d',
    '#1155cc',
    '#674ea7',
    '#ffffff',
    '#ffe599',
];

const RawFontFamily = FontFamily.extend({
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: { fontFamily: rawStyleAttribute('fontFamily', 'font-family', true) },
            },
        ];
    },
});

const RawFontSize = FontSize.extend({
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: { fontSize: rawStyleAttribute('fontSize', 'font-size') },
            },
        ];
    },
});

const RawColor = Color.extend({
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: { color: rawStyleAttribute('color', 'color') },
            },
        ];
    },
});

const RawBackgroundColor = BackgroundColor.extend({
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    backgroundColor: rawStyleAttribute('backgroundColor', 'background-color'),
                },
            },
        ];
    },
});

export interface DocumentExtensionsOptions {
    textAlignTypes?: readonly string[];
}

const DEFAULT_TEXT_ALIGN_TYPES = BLOCK_STYLE_TYPES;

export function createDocumentExtensions(options: DocumentExtensionsOptions = {}): AnyExtension[] {
    return [
        StarterKit.configure({
            link: false,
            code: false,
            codeBlock: false,
            paragraph: false,
            trailingNode: false,
            heading: { levels: [1, 2, 3] },
        }),
        DocumentParagraph,
        Indent,
        ListSafeHeading,
        TextAlign.configure({ types: [...DEFAULT_TEXT_ALIGN_TYPES, ...(options.textAlignTypes ?? [])] }),
        LegacyTextStyle,
        RawFontFamily,
        RawFontSize,
        RawColor,
        RawBackgroundColor,
        BlockTextStyle,
        Highlight.configure({ multicolor: false }),
        TableKit.configure({ table: { resizable: false } }),
        TableStyle,
        WordSpaces,
    ];
}

type DocumentSchema = ReturnType<typeof getSchema>;

export function canonicalEditorHtml(html: string, source: AnyExtension[] | DocumentSchema): string {
    const schema = Array.isArray(source) ? getSchema(source) : source;

    return canonicalSpacesInHtml(
        getHTMLFromFragment(createDocument(html, schema, DOCUMENT_PARSE_OPTIONS).content, schema),
    );
}
