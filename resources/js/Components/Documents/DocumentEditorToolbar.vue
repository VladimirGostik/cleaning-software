<script setup lang="ts">
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Editor } from '@tiptap/vue-3';
import ArrowUturnLeftIcon from '~icons/material-symbols/undo-outline-rounded';
import ArrowUturnRightIcon from '~icons/material-symbols/redo-outline-rounded';
import Bars3BottomLeftIcon from '~icons/material-symbols/format-align-left-outline-rounded';
import Bars3BottomRightIcon from '~icons/material-symbols/format-align-right-outline-rounded';
import Bars3Icon from '~icons/material-symbols/format-align-center-outline-rounded';
import Bars4Icon from '~icons/material-symbols/format-align-justify-outline-rounded';
import ListBulletIcon from '~icons/material-symbols/format-list-bulleted-outline-rounded';
import MinusIcon from '~icons/material-symbols/remove-outline-rounded';
import NumberedListIcon from '~icons/material-symbols/format-list-numbered-outline-rounded';
import {
    DEFAULT_DOCUMENT_FONT,
    DEFAULT_DOCUMENT_FONT_SIZE_PT,
    DOCUMENT_COLORS,
    DOCUMENT_FONT_SIZES_PT,
    DOCUMENT_FONTS,
    type DocumentFont,
} from '@/Components/Documents/editor/documentEditorKit';
import {
    DOCUMENT_TOOLBAR,
    PARAGRAPH_STYLES,
    getFontFamily,
    getFontSizePt,
    getParagraphStyle,
    isParagraphStyleDisabled,
    isToolbarActionActive,
    isToolbarActionDisabled,
    runToolbarAction,
    setFontFamily,
    setFontSizePt,
    setHighlightColor,
    setParagraphStyle,
    setTextColor,
    type DocumentToolbarActionId,
    type DocumentToolbarItemId,
    type ParagraphStyle,
} from '@/Components/Documents/editor/documentToolbar';
import ToolbarButton from '@/Components/Documents/editor/ToolbarButton.vue';
import ToolbarColorPicker from '@/Components/Documents/editor/ToolbarColorPicker.vue';
import ToolbarSelect, { type ToolbarSelectOption } from '@/Components/Documents/editor/ToolbarSelect.vue';

const props = defineProps<{
    editor: Editor;
}>();

const { t } = useI18n();

interface ActionVisual {
    label: string;
    icon?: Component;
    glyph?: string;
    glyphClass?: string;
}

const ACTIONS: Record<DocumentToolbarActionId, ActionVisual> = {
    undo: { label: 'editor_undo', icon: ArrowUturnLeftIcon },
    redo: { label: 'editor_redo', icon: ArrowUturnRightIcon },
    bold: { label: 'editor_bold', glyph: 'B', glyphClass: 'font-medium' },
    italic: { label: 'editor_italic', glyph: 'I', glyphClass: 'italic' },
    underline: { label: 'editor_underline', glyph: 'U', glyphClass: 'underline' },
    strike: { label: 'editor_strike', glyph: 'S', glyphClass: 'line-through' },
    alignLeft: { label: 'document_align_left', icon: Bars3BottomLeftIcon },
    alignCenter: { label: 'document_align_center', icon: Bars3Icon },
    alignRight: { label: 'document_align_right', icon: Bars3BottomRightIcon },
    alignJustify: { label: 'document_align_justify', icon: Bars4Icon },
    bulletList: { label: 'editor_bullet_list', icon: ListBulletIcon },
    orderedList: { label: 'editor_ordered_list', icon: NumberedListIcon },
    outdent: { label: 'editor_outdent', glyph: '⇤' },
    indent: { label: 'editor_indent', glyph: '⇥' },
    horizontalRule: { label: 'editor_hr', icon: MinusIcon },
    clearFormatting: { label: 'editor_clear_format', glyph: 'Tx' },
};

const PRESSABLE = new Set<DocumentToolbarItemId>([
    'bold',
    'italic',
    'underline',
    'strike',
    'alignLeft',
    'alignCenter',
    'alignRight',
    'alignJustify',
    'bulletList',
    'orderedList',
]);

function formatPt(pt: number): string {
    return String(pt).replace('.', ',');
}

function paragraphStyleLabel(style: ParagraphStyle): string {
    return t(style === 'normal' ? 'editor_paragraph_normal' : `editor_h${style.slice(1)}`);
}

const paragraphOptions = computed<ToolbarSelectOption[]>(() =>
    PARAGRAPH_STYLES.map((style) => ({
        value: style,
        label: paragraphStyleLabel(style),
        disabled: isParagraphStyleDisabled(props.editor, style),
    })),
);

const fontOptions = computed<ToolbarSelectOption[]>(() =>
    DOCUMENT_FONTS.map((font) => ({
        value: font,
        label: font === DEFAULT_DOCUMENT_FONT ? t('editor_font_default', { value: font }) : font,
    })),
);

const currentSizePt = computed(() => getFontSizePt(props.editor) ?? DEFAULT_DOCUMENT_FONT_SIZE_PT);

const sizeOptions = computed<ToolbarSelectOption[]>(() => {
    const sizes: number[] = [...DOCUMENT_FONT_SIZES_PT];
    if (!sizes.includes(currentSizePt.value)) sizes.push(currentSizePt.value);

    return sizes
        .sort((a, b) => a - b)
        .map((pt) => ({
            value: String(pt),
            label:
                pt === DEFAULT_DOCUMENT_FONT_SIZE_PT ? t('editor_size_default', { value: formatPt(pt) }) : formatPt(pt),
        }));
});

const paragraphStyle = computed(() => getParagraphStyle(props.editor));
const fontFamily = computed(() => getFontFamily(props.editor) ?? DEFAULT_DOCUMENT_FONT);

function action(id: DocumentToolbarItemId): DocumentToolbarActionId {
    return id as DocumentToolbarActionId;
}

function isActive(id: DocumentToolbarItemId): boolean | undefined {
    return PRESSABLE.has(id) ? isToolbarActionActive(props.editor, action(id)) : undefined;
}

function pickColor(id: 'textColor' | 'highlight', color: string | null): void {
    if (id === 'textColor') setTextColor(props.editor, color);
    else setHighlightColor(props.editor, color);
}
</script>

<template>
    <div
        role="toolbar"
        :aria-label="t('document_toolbar')"
        class="flex flex-wrap items-center gap-x-2 gap-y-1 px-2 py-1"
    >
        <template v-for="(group, groupIndex) in DOCUMENT_TOOLBAR" :key="groupIndex">
            <div class="flex items-center gap-1 rounded-field bg-base-200/60 px-1">
                <template v-for="id in group" :key="id">
                    <ToolbarSelect
                        v-if="id === 'paragraphStyle'"
                        :model-value="paragraphStyle"
                        :label="t('editor_paragraph_style')"
                        :options="paragraphOptions"
                        @update:model-value="setParagraphStyle(editor, $event as ParagraphStyle)"
                    />
                    <ToolbarSelect
                        v-else-if="id === 'fontFamily'"
                        :model-value="fontFamily"
                        :label="t('editor_font')"
                        :options="fontOptions"
                        @update:model-value="setFontFamily(editor, $event as DocumentFont)"
                    />
                    <ToolbarSelect
                        v-else-if="id === 'fontSize'"
                        :model-value="String(currentSizePt)"
                        :label="t('editor_size')"
                        :options="sizeOptions"
                        @update:model-value="setFontSizePt(editor, Number($event))"
                    />
                    <ToolbarColorPicker
                        v-else-if="id === 'textColor' || id === 'highlight'"
                        :label="t(id === 'textColor' ? 'editor_text_color' : 'editor_highlight')"
                        :colors="DOCUMENT_COLORS"
                        @pick="pickColor(id, $event)"
                    >
                        <span :class="id === 'textColor' ? 'font-medium' : 'bg-warning/40 px-0.5'">A</span>
                    </ToolbarColorPicker>
                    <ToolbarButton
                        v-else
                        :label="t(ACTIONS[action(id)].label)"
                        :pressed="isActive(id)"
                        :disabled="isToolbarActionDisabled(editor, action(id))"
                        @click="runToolbarAction(editor, action(id))"
                    >
                        <component :is="ACTIONS[action(id)].icon" v-if="ACTIONS[action(id)].icon" />
                        <span v-else :class="ACTIONS[action(id)].glyphClass" aria-hidden="true">{{
                            ACTIONS[action(id)].glyph
                        }}</span>
                    </ToolbarButton>
                </template>
            </div>
        </template>
    </div>
</template>
