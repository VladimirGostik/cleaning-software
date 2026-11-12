<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CameraIcon from '~icons/material-symbols/photo-camera-outline-rounded';
import PaperClipIcon from '~icons/material-symbols/attach-file-outline-rounded';
import SendIcon from '~icons/material-symbols/send-outline-rounded';
import AssistantAttachmentChip from '@/Components/Assistant/AssistantAttachmentChip.vue';
import FilePickerButton from '@/Components/Forms/FilePickerButton.vue';
import type { AssistantAttachmentItem } from '@/Composables/useAssistantAttachments';
import { ASSISTANT_ATTACHMENT_MIMES, MAX_MESSAGE_CHARS } from '@/utils/assistant';

const props = defineProps<{
    items: AssistantAttachmentItem[];
    error: string | null;
    disabled: boolean;
    sending: boolean;
    isFull: boolean;
}>();

const emit = defineEmits<{
    add: [files: File[]];
    remove: [id: string];
    submit: [text: string];
}>();

const { t } = useI18n();

const draft = ref('');
const textarea = useTemplateRef<HTMLTextAreaElement>('textarea');

const accept = ASSISTANT_ATTACHMENT_MIMES.join(',');
const cameraAccept = ASSISTANT_ATTACHMENT_MIMES.filter((mime) => mime.startsWith('image/')).join(',');

const canSend = computed(
    () => !props.disabled && !props.sending && (draft.value.trim() !== '' || props.items.length > 0),
);

function submit(): void {
    if (!canSend.value) return;
    const text = draft.value;
    draft.value = '';
    emit('submit', text);
}

function onEnter(event: KeyboardEvent): void {
    if (event.shiftKey || event.isComposing) return;
    event.preventDefault();
    submit();
}

function onPaste(event: Event): void {
    const { clipboardData } = event as Event & {
        clipboardData: { files: ArrayLike<File>; getData: (type: string) => string } | null;
    };
    const files = Array.from(clipboardData?.files ?? []);
    if (files.length === 0) return;
    if (clipboardData?.getData('text/plain').trim() === '') event.preventDefault();
    emit('add', files);
}

// field-sizing: content is not supported everywhere (Safari, Firefox), so the composer grows here.
function resize(): void {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight + el.offsetHeight - el.clientHeight}px`;
}

function restore(text: string): void {
    draft.value = text;
    void nextTick(() => textarea.value?.focus());
}

watch(draft, () => nextTick(resize));
onMounted(() => textarea.value?.focus());

defineExpose({ restore });
</script>

<template>
    <footer class="space-y-2 p-3">
        <ul v-if="items.length > 0" class="flex flex-wrap gap-2" :aria-label="t('assistant_attach_file')">
            <li v-for="item in items" :key="item.id" class="max-w-full">
                <AssistantAttachmentChip
                    :name="item.name"
                    :kind="item.kind"
                    :preview-url="item.previewUrl"
                    removable
                    @remove="emit('remove', item.id)"
                />
            </li>
        </ul>
        <p v-if="error" class="text-xs text-error" role="alert">{{ error }}</p>

        <div class="flex items-end gap-2">
            <FilePickerButton
                class="btn btn-ghost btn-square"
                icon-only
                multiple
                :label="t('assistant_attach_file')"
                :accept="accept"
                :disabled="disabled || isFull"
                @selected="emit('add', $event)"
            >
                <PaperClipIcon />
            </FilePickerButton>
            <FilePickerButton
                class="btn btn-ghost btn-square hidden pointer-coarse:inline-flex"
                icon-only
                capture="environment"
                :label="t('assistant_take_photo')"
                :accept="cameraAccept"
                :disabled="disabled || isFull"
                @selected="emit('add', $event)"
            >
                <CameraIcon />
            </FilePickerButton>
            <textarea
                ref="textarea"
                v-model="draft"
                rows="1"
                class="textarea max-h-40 flex-1 resize-none placeholder:truncate"
                :maxlength="MAX_MESSAGE_CHARS"
                :placeholder="t('assistant_placeholder')"
                :aria-label="t('assistant_placeholder')"
                :disabled="disabled"
                @keydown.enter="onEnter"
                @paste="onPaste"
            />
            <button
                type="button"
                class="btn btn-primary btn-square"
                :aria-label="t('assistant_send')"
                :title="t('assistant_send')"
                :disabled="!canSend"
                @click="submit"
            >
                <SendIcon />
            </button>
        </div>
    </footer>
</template>
