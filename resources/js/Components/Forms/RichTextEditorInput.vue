<script setup lang="ts">
import { KG_ICONS } from '@/icons/kegosIcons';
import type { Component } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import BoldIcon from '~icons/material-symbols/format-bold-outline-rounded';
import ItalicIcon from '~icons/material-symbols/format-italic-outline-rounded';
import StrikeIcon from '~icons/material-symbols/format-strikethrough-outline-rounded';
import H1Icon from '~icons/material-symbols/format-h1-outline-rounded';
import H2Icon from '~icons/material-symbols/format-h2-outline-rounded';
import H3Icon from '~icons/material-symbols/format-h3-outline-rounded';
import BulletListIcon from '~icons/material-symbols/format-list-bulleted-outline-rounded';
import OrderedListIcon from '~icons/material-symbols/format-list-numbered-outline-rounded';
import QuoteIcon from '~icons/material-symbols/format-quote-outline-rounded';
import CodeBlockIcon from '~icons/material-symbols/code-blocks-outline-rounded';
import UndoIcon from '~icons/material-symbols/undo-outline-rounded';
import RedoIcon from '~icons/material-symbols/redo-outline-rounded';
import { useI18n } from 'vue-i18n';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        placeholder?: string;
        error?: string | null;
        disabled?: boolean;
        uploadEndpoint?: string;
        maxImageSizeKb?: number;
    }>(),
    {
        placeholder: '',
        error: null,
        disabled: false,
        uploadEndpoint: '/uploads',
        maxImageSizeKb: 10240,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const { t } = useI18n();

const isUploading = ref(false);
const uploadError = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit,
        Image.configure({
            HTMLAttributes: {
                class: 'rounded-tile-sm max-w-full h-auto',
            },
        }),
        Link.configure({
            openOnClick: false,
            autolink: true,
            HTMLAttributes: {
                class: 'link link-primary',
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose max-w-none min-h-48 px-3 py-2 focus:outline-none',
        },
        handleDrop: (_view, event) => {
            const files = event.dataTransfer?.files;
            if (files && files.length > 0 && files[0].type.startsWith('image/')) {
                event.preventDefault();
                uploadImage(files[0]);
                return true;
            }
            return false;
        },
        handlePaste: (_view, event) => {
            const items = event.clipboardData?.items;
            if (!items) return false;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    if (file) {
                        event.preventDefault();
                        uploadImage(file);
                        return true;
                    }
                }
            }
            return false;
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (!editor.value) return;
        if (val !== editor.value.getHTML()) {
            editor.value.commands.setContent(val, { emitUpdate: false });
        }
    },
);

watch(
    () => props.disabled,
    (val) => {
        editor.value?.setEditable(!val);
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function uploadImage(file: File): Promise<void> {
    uploadError.value = null;

    if (file.size > props.maxImageSizeKb * 1024) {
        uploadError.value = t('file_too_large', { max: `${props.maxImageSizeKb} KB` });
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch(props.uploadEndpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: formData,
        });
        if (!res.ok) throw new Error('upload failed');
        const data = (await res.json()) as { uuid: string; url: string };
        editor.value?.chain().focus().setImage({ src: data.url, alt: file.name }).run();
        // tag inserted node with data-media-uuid for backend claim
        const html = editor.value?.getHTML() ?? '';
        const tagged = html.replace(
            `<img src="${data.url}" alt="${file.name}"`,
            `<img src="${data.url}" alt="${file.name}" data-media-uuid="${data.uuid}"`,
        );
        editor.value?.commands.setContent(tagged, { emitUpdate: true });
    } catch {
        uploadError.value = t('upload_failed');
    } finally {
        isUploading.value = false;
    }
}

function onPickImage(): void {
    fileInputRef.value?.click();
}

function onFileInputChange(e: Event): void {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        uploadImage(input.files[0]);
        input.value = '';
    }
}

interface ToolbarButton {
    key: string;
    icon: Component;
    title: string;
    active?: () => boolean | undefined;
    run: () => void;
}

const toolbarGroups: ToolbarButton[][] = [
    [
        {
            key: 'bold',
            icon: BoldIcon,
            title: 'editor_bold',
            active: () => editor.value?.isActive('bold'),
            run: () => editor.value?.chain().focus().toggleBold().run(),
        },
        {
            key: 'italic',
            icon: ItalicIcon,
            title: 'editor_italic',
            active: () => editor.value?.isActive('italic'),
            run: () => editor.value?.chain().focus().toggleItalic().run(),
        },
        {
            key: 'strike',
            icon: StrikeIcon,
            title: 'editor_strike',
            active: () => editor.value?.isActive('strike'),
            run: () => editor.value?.chain().focus().toggleStrike().run(),
        },
    ],
    [
        {
            key: 'h1',
            icon: H1Icon,
            title: 'editor_h1',
            active: () => editor.value?.isActive('heading', { level: 1 }),
            run: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
        },
        {
            key: 'h2',
            icon: H2Icon,
            title: 'editor_h2',
            active: () => editor.value?.isActive('heading', { level: 2 }),
            run: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        },
        {
            key: 'h3',
            icon: H3Icon,
            title: 'editor_h3',
            active: () => editor.value?.isActive('heading', { level: 3 }),
            run: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        },
    ],
    [
        {
            key: 'bulletList',
            icon: BulletListIcon,
            title: 'editor_bullet_list',
            active: () => editor.value?.isActive('bulletList'),
            run: () => editor.value?.chain().focus().toggleBulletList().run(),
        },
        {
            key: 'orderedList',
            icon: OrderedListIcon,
            title: 'editor_ordered_list',
            active: () => editor.value?.isActive('orderedList'),
            run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        },
        {
            key: 'blockquote',
            icon: QuoteIcon,
            title: 'editor_blockquote',
            active: () => editor.value?.isActive('blockquote'),
            run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        },
        {
            key: 'codeBlock',
            icon: CodeBlockIcon,
            title: 'editor_code_block',
            active: () => editor.value?.isActive('codeBlock'),
            run: () => editor.value?.chain().focus().toggleCodeBlock().run(),
        },
    ],
];

const historyButtons: ToolbarButton[] = [
    {
        key: 'undo',
        icon: UndoIcon,
        title: 'editor_undo',
        run: () => editor.value?.chain().focus().undo().run(),
    },
    {
        key: 'redo',
        icon: RedoIcon,
        title: 'editor_redo',
        run: () => editor.value?.chain().focus().redo().run(),
    },
];

function setLink(): void {
    if (!editor.value) return;
    const prev = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt(t('editor_link_url'), prev ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}
</script>

<template>
    <div>
        <div
            class="rounded-tile bg-(--kg-control-bg) focus-within:outline-2 focus-within:outline-offset-2"
            :class="[
                error ? 'outline-2 outline-error' : 'focus-within:outline-primary',
                { 'pointer-events-none opacity-60': disabled },
            ]"
        >
            <div class="flex flex-wrap items-center gap-1 p-1">
                <template v-for="group in toolbarGroups" :key="group[0].key">
                    <button
                        v-for="button in group"
                        :key="button.key"
                        type="button"
                        class="btn btn-ghost btn-square"
                        :class="{ 'btn-active': button.active?.() }"
                        :title="t(button.title)"
                        :aria-label="t(button.title)"
                        :aria-pressed="button.active ? !!button.active() : undefined"
                        @click="button.run"
                    >
                        <component :is="button.icon" aria-hidden="true" />
                    </button>
                </template>
                <button
                    type="button"
                    class="btn btn-ghost btn-square"
                    :class="{ 'btn-active': editor?.isActive('link') }"
                    :title="t('editor_link')"
                    :aria-label="t('editor_link')"
                    @click="setLink"
                >
                    <KG_ICONS.adminAction.link />
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-square"
                    :title="t('editor_image')"
                    :aria-label="t('editor_image')"
                    :disabled="isUploading"
                    @click="onPickImage"
                >
                    <span v-if="isUploading" class="loading loading-spinner loading-xs" />
                    <KG_ICONS.adminAction.image v-else />
                </button>
                <input ref="fileInputRef" type="file" class="hidden" accept="image/*" @change="onFileInputChange" />
                <button
                    v-for="button in historyButtons"
                    :key="button.key"
                    type="button"
                    class="btn btn-ghost btn-square"
                    :title="t(button.title)"
                    :aria-label="t(button.title)"
                    @click="button.run"
                >
                    <component :is="button.icon" aria-hidden="true" />
                </button>
            </div>

            <div class="overflow-hidden rounded-b-tile bg-base-100">
                <EditorContent :editor="editor" />
            </div>
        </div>

        <p v-if="error" class="mt-1 text-sm text-error">{{ error }}</p>
        <p v-if="uploadError" class="mt-1 text-sm text-error">{{ uploadError }}</p>
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--color-muted);
    pointer-events: none;
    height: 0;
}
</style>
