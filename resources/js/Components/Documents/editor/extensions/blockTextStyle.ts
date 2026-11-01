import { Extension } from '@tiptap/vue-3';
import { rawStyleAttribute } from './rawStyle';

export const BLOCK_STYLE_TYPES = ['heading', 'paragraph', 'listItem'];

export const BlockTextStyle = Extension.create({
    name: 'blockTextStyle',

    addGlobalAttributes() {
        return [
            {
                types: BLOCK_STYLE_TYPES,
                attributes: {
                    fontFamily: rawStyleAttribute('fontFamily', 'font-family', true),
                    fontSize: rawStyleAttribute('fontSize', 'font-size'),
                    color: rawStyleAttribute('color', 'color'),
                    backgroundColor: rawStyleAttribute('backgroundColor', 'background-color'),
                },
            },
        ];
    },
});
