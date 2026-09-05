<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

const props = withDefaults(defineProps<{
    modelValue: string;
    placeholder?: string;
    error?: string | null;
    disabled?: boolean;
    uploadEndpoint?: string;
    maxImageSizeKb?: number;
}>(), {
    placeholder: '',
    error: null,
    disabled: false,
    uploadEndpoint: '/uploads',
    maxImageSizeKb: 10240,
});

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
                class: 'rounded max-w-full h-auto',
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

watch(() => props.modelValue, (val) => {
    if (!editor.value) return;
    if (val !== editor.value.getHTML()) {
        editor.value.commands.setContent(val, { emitUpdate: false });
    }
});

watch(() => props.disabled, (val) => {
    editor.value?.setEditable(!val);
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function uploadImage(file: File): Promise<void> {
    uploadError.value = null;

    if (file.size > props.maxImageSizeKb * 1024) {
        uploadError.value = t('app.file_too_large', { max: `${props.maxImageSizeKb} KB` });
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
                'Accept': 'application/json',
            },
            body: formData,
        });
        if (!res.ok) throw new Error('upload failed');
        const data = await res.json() as { uuid: string; url: string };
        editor.value
            ?.chain()
            .focus()
            .setImage({ src: data.url, alt: file.name })
            .run();
        // tag inserted node with data-media-uuid for backend claim
        const html = editor.value?.getHTML() ?? '';
        const tagged = html.replace(
            `<img src="${data.url}" alt="${file.name}"`,
            `<img src="${data.url}" alt="${file.name}" data-media-uuid="${data.uuid}"`,
        );
        editor.value?.commands.setContent(tagged, { emitUpdate: true });
    } catch {
        uploadError.value = t('app.upload_failed');
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

function setLink(): void {
    if (!editor.value) return;
    const prev = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt(t('app.editor_link_url'), prev ?? 'https://');
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
            class="border border-base-300 rounded-box overflow-hidden bg-base-100"
            :class="{ 'border-error': error, 'opacity-60 pointer-events-none': disabled }"
        >
            <div class="flex flex-wrap items-center gap-1 px-2 py-1 border-b border-base-300 bg-base-200/50">
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('bold') }"
                    :title="t('app.editor_bold')"
                    @click="editor?.chain().focus().toggleBold().run()"
                >
                    <strong>B</strong>
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs italic"
                    :class="{ 'btn-active': editor?.isActive('italic') }"
                    :title="t('app.editor_italic')"
                    @click="editor?.chain().focus().toggleItalic().run()"
                >
                    I
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs line-through"
                    :class="{ 'btn-active': editor?.isActive('strike') }"
                    :title="t('app.editor_strike')"
                    @click="editor?.chain().focus().toggleStrike().run()"
                >
                    S
                </button>
                <span class="divider divider-horizontal mx-0" />
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('heading', { level: 1 }) }"
                    :title="t('app.editor_h1')"
                    @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
                >
                    H1
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('heading', { level: 2 }) }"
                    :title="t('app.editor_h2')"
                    @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                >
                    H2
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('heading', { level: 3 }) }"
                    :title="t('app.editor_h3')"
                    @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                >
                    H3
                </button>
                <span class="divider divider-horizontal mx-0" />
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('bulletList') }"
                    :title="t('app.editor_bullet_list')"
                    @click="editor?.chain().focus().toggleBulletList().run()"
                >
                    •
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('orderedList') }"
                    :title="t('app.editor_ordered_list')"
                    @click="editor?.chain().focus().toggleOrderedList().run()"
                >
                    1.
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('blockquote') }"
                    :title="t('app.editor_blockquote')"
                    @click="editor?.chain().focus().toggleBlockquote().run()"
                >
                    “”
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('codeBlock') }"
                    :title="t('app.editor_code_block')"
                    @click="editor?.chain().focus().toggleCodeBlock().run()"
                >
                    {}
                </button>
                <span class="divider divider-horizontal mx-0" />
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :class="{ 'btn-active': editor?.isActive('link') }"
                    :title="t('app.editor_link')"
                    @click="setLink"
                >
                    🔗
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :title="t('app.editor_image')"
                    :disabled="isUploading"
                    @click="onPickImage"
                >
                    <span v-if="isUploading" class="loading loading-spinner loading-xs" />
                    <span v-else>🖼</span>
                </button>
                <input
                    ref="fileInputRef"
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="onFileInputChange"
                />
                <span class="divider divider-horizontal mx-0" />
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :title="t('app.editor_undo')"
                    @click="editor?.chain().focus().undo().run()"
                >
                    ↶
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs"
                    :title="t('app.editor_redo')"
                    @click="editor?.chain().focus().redo().run()"
                >
                    ↷
                </button>
            </div>

            <EditorContent :editor="editor" />
        </div>

        <p v-if="error" class="text-error text-sm mt-1">{{ error }}</p>
        <p v-if="uploadError" class="text-error text-sm mt-1">{{ uploadError }}</p>
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: oklch(var(--bc) / 0.4);
    pointer-events: none;
    height: 0;
}
</style>
