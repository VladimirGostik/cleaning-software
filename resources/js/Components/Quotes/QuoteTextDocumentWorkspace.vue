<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XMarkIcon from '~icons/material-symbols/close-outline-rounded';

import DocumentFrameToggles from '@/Components/Documents/DocumentFrameToggles.vue';
import DocumentTokenEditor from '@/Components/Documents/DocumentTokenEditor.vue';
import DocumentTokenPicker from '@/Components/Documents/DocumentTokenPicker.vue';
import type { TokenPreview } from '@/Components/Documents/editor/extensions/templateTokenHtml';
import { useFullscreenWorkspace } from '@/Composables/useFullscreenWorkspace';

defineProps<{
    tokens: readonly App.Data.DocumentTemplates.DocumentTokenData[];
    letterhead: App.Data.Documents.DocumentLetterheadData;
    footer: string[];
    error?: string | null;
    settingsUrl?: string | null;
    preview: TokenPreview | null;
}>();

const body = defineModel<string>('body', { required: true });
const showHeader = defineModel<boolean>('showHeader', { required: true });
const showFooter = defineModel<boolean>('showFooter', { required: true });

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const root = ref<HTMLElement | null>(null);
const editor = ref<InstanceType<typeof DocumentTokenEditor> | null>(null);
const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;

const { fullscreen } = useFullscreenWorkspace();

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [contenteditable="true"]';

function focusEditor(attempt = 0): void {
    const target = root.value?.querySelector<HTMLElement>('[contenteditable="true"]');
    if (target) {
        target.focus();
        return;
    }
    if (attempt < 10) window.requestAnimationFrame(() => focusEditor(attempt + 1));
    else root.value?.focus();
}

function trapTab(event: KeyboardEvent): void {
    if (event.key !== 'Tab' || !root.value) return;

    const items = Array.from(root.value.querySelectorAll<HTMLElement>(FOCUSABLE));
    const first = items[0];
    const last = items[items.length - 1];
    if (!first || !last) return;

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

onMounted(async () => {
    fullscreen.value = true;
    await nextTick();
    focusEditor();
});

onBeforeUnmount(() => opener?.focus());

watch(fullscreen, (on) => {
    if (!on) emit('close');
});
</script>

<template>
    <Teleport to="body">
        <div
            ref="root"
            class="document-workspace--fullscreen flex flex-col gap-3"
            role="dialog"
            aria-modal="true"
            :aria-label="t('quote_text_workspace_title')"
            tabindex="-1"
            @keydown="trapTab"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">{{ t('quote_text_workspace_title') }}</h2>
                <div class="flex flex-wrap items-center gap-4">
                    <DocumentFrameToggles v-model:show-header="showHeader" v-model:show-footer="showFooter" />
                    <button type="button" class="btn btn-sm btn-primary" @click="emit('close')">
                        <XMarkIcon class="size-5" aria-hidden="true" />
                        {{ t('quote_text_workspace_done') }}
                    </button>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                <DocumentTokenEditor
                    ref="editor"
                    v-model="body"
                    :tokens="tokens"
                    :error="error"
                    :letterhead="showHeader ? letterhead : null"
                    :footer="showFooter ? footer : []"
                    :settings-url="settingsUrl"
                    :preview="preview"
                    variant="kegos"
                />
                <aside class="min-h-0 overflow-y-auto rounded-box border border-base-300 bg-base-100 p-3">
                    <DocumentTokenPicker :tokens="tokens" @pick="editor?.insertToken($event)" />
                </aside>
            </div>
        </div>
    </Teleport>
</template>
