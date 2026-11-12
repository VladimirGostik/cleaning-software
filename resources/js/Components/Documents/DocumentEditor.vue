<script setup lang="ts">
import { EditorContent, type Editor } from '@tiptap/vue-3';
import DocumentEditorToolbar from '@/Components/Documents/DocumentEditorToolbar.vue';
import DocumentFrame from '@/Components/Documents/DocumentFrame.vue';

withDefaults(
    defineProps<{
        editor: Editor | undefined;
        letterhead?: App.Data.Documents.DocumentLetterheadData | null;
        footer?: string[];
        settingsUrl?: string | null;
        variant?: 'letterhead' | 'kegos';
    }>(),
    { letterhead: null, footer: () => [], settingsUrl: null, variant: 'letterhead' },
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div
            v-if="editor?.isEditable || $slots['toolbar-end']"
            class="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-base-300 bg-base-100"
        >
            <DocumentEditorToolbar v-if="editor?.isEditable" :editor="editor" class="flex-1" />
            <div v-if="$slots['toolbar-end']" class="px-2">
                <slot name="toolbar-end" />
            </div>
        </div>
        <div class="document-desk min-h-0 flex-1">
            <article class="document-page flex flex-col" :class="{ 'document-page--quote': variant === 'kegos' }">
                <DocumentFrame
                    part="header"
                    :letterhead="letterhead"
                    :variant="variant"
                    :settings-url="settingsUrl"
                    labelled
                />
                <EditorContent v-if="editor" :editor="editor" class="flex flex-1 flex-col" />
                <DocumentFrame part="footer" :footer="footer" :variant="variant" :settings-url="settingsUrl" labelled />
            </article>
        </div>
    </div>
</template>
