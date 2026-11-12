<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentEditor from '@/Components/Documents/DocumentEditor.vue';
import { TemplateBlockToken, TemplateToken } from '@/Components/Documents/editor/extensions/templateToken';
import {
    editorToStoredHtml,
    storedToEditorHtml,
    type TokenPreview,
} from '@/Components/Documents/editor/extensions/templateTokenHtml';
import { DOCUMENT_PARSE_OPTIONS } from '@/Components/Documents/editor/documentEditorKit';
import { useDocumentEditor } from '@/Composables/useDocumentEditor';

type DocumentTokenDef = App.Data.DocumentTemplates.DocumentTokenData;

const props = withDefaults(
    defineProps<{
        modelValue: string;
        tokens: readonly DocumentTokenDef[];
        error?: string | null;
        readonly?: boolean;
        ariaLabel?: string;
        letterhead?: App.Data.Documents.DocumentLetterheadData | null;
        footer?: string[];
        settingsUrl?: string | null;
        variant?: 'letterhead' | 'kegos';
        preview?: TokenPreview | null;
    }>(),
    {
        error: null,
        readonly: false,
        ariaLabel: undefined,
        letterhead: null,
        footer: () => [],
        settingsUrl: null,
        variant: 'letterhead',
        preview: null,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();

const defsByKey = computed(() => Object.fromEntries(props.tokens.map((def) => [def.key, def])));

const { editor } = useDocumentEditor({
    content: () => props.modelValue,
    onChange: (html) => emit('update:modelValue', html),
    ariaLabel: props.ariaLabel ?? t('document_editor_label'),
    extraExtensions: [TemplateToken, TemplateBlockToken],
    textAlignTypes: ['templateBlock'],
    editable: !props.readonly,
    toEditorHtml: (stored) => storedToEditorHtml(stored, defsByKey.value, props.preview),
    fromEditorHtml: editorToStoredHtml,
});

watch(
    () => props.preview,
    () => {
        editor.value?.commands.setContent(storedToEditorHtml(props.modelValue, defsByKey.value, props.preview), {
            emitUpdate: false,
            parseOptions: DOCUMENT_PARSE_OPTIONS,
        });
    },
);

watch(
    () => props.readonly,
    (readonly) => editor.value?.setEditable(!readonly),
);

function insertToken(def: DocumentTokenDef): void {
    const instance = editor.value;
    if (!instance) return;

    const attrs = { key: def.key, label: def.label, kind: def.kind };
    const { $from } = instance.state.selection;

    if (def.kind === 'block') {
        const empty = $from.parent.isTextblock && $from.parent.content.size === 0;
        const after = $from.depth > 0 ? $from.after() : $from.pos;
        const range = empty ? { from: $from.before(), to: after } : { from: after, to: after };
        instance.chain().focus().insertContentAt(range, { type: 'templateBlock', attrs }).run();
        return;
    }

    if (def.kind === 'flag' && $from.parent.isTextblock) {
        instance.chain().focus().insertContentAt($from.start(), { type: 'templateToken', attrs }).run();
        return;
    }

    instance.chain().focus().insertContent({ type: 'templateToken', attrs }).run();
}

function focusToken(key: string): boolean {
    const instance = editor.value;
    if (!instance) return false;

    let position: number | null = null;
    instance.state.doc.descendants((node, pos) => {
        if (position !== null) return false;
        if ((node.type.name === 'templateToken' || node.type.name === 'templateBlock') && node.attrs.key === key) {
            position = pos;
            return false;
        }
        return true;
    });

    if (position === null) return false;

    return instance.chain().focus().setNodeSelection(position).scrollIntoView().run();
}

defineExpose({ insertToken, focusToken });
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div
            class="min-h-0 flex-1 overflow-hidden rounded-box bg-base-100"
            :class="{ 'outline outline-2 outline-offset-2 outline-error': error }"
        >
            <DocumentEditor
                :editor="editor"
                :letterhead="letterhead"
                :footer="footer"
                :variant="variant"
                :settings-url="settingsUrl"
            />
        </div>

        <p v-if="error" class="text-error text-sm" role="alert">{{ error }}</p>
    </div>
</template>
