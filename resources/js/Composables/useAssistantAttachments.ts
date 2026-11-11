import { computed, onScopeDispose, shallowRef, type ComputedRef, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES,
    ASSISTANT_ATTACHMENT_MAX_FILES,
    ASSISTANT_IMAGE_MAX_BYTES,
    ASSISTANT_PDF_MAX_BYTES,
    assistantAttachmentKind,
    assistantAttachmentRejection,
    type AssistantAttachmentKind,
    type AssistantAttachmentRejection,
} from '@/utils/assistant';

export interface AssistantAttachmentItem {
    id: string;
    file: File;
    name: string;
    kind: AssistantAttachmentKind;
    previewUrl: string | null;
}

const MIB = 1024 * 1024;

export function useAssistantAttachments(): {
    items: Ref<AssistantAttachmentItem[]>;
    error: Ref<string | null>;
    isFull: ComputedRef<boolean>;
    add: (files: File[]) => void;
    remove: (id: string) => void;
    clear: () => void;
    take: () => File[];
} {
    const { t } = useI18n();

    const items = shallowRef<AssistantAttachmentItem[]>([]);
    const error = shallowRef<string | null>(null);
    const isFull = computed(() => items.value.length >= ASSISTANT_ATTACHMENT_MAX_FILES);
    let counter = 0;

    function message(reason: AssistantAttachmentRejection, kind: AssistantAttachmentKind | null): string {
        if (reason === 'invalid_type') return t('assistant_attachment_invalid_type');
        if (reason === 'too_many') return t('assistant_attachment_too_many', { max: ASSISTANT_ATTACHMENT_MAX_FILES });
        if (reason === 'total_too_large') {
            return t('assistant_files_total_too_large', { max: ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES / MIB });
        }

        return t('assistant_file_too_large', {
            max: (kind === 'pdf' ? ASSISTANT_PDF_MAX_BYTES : ASSISTANT_IMAGE_MAX_BYTES) / MIB,
        });
    }

    function release(list: readonly AssistantAttachmentItem[]): void {
        list.forEach((item) => {
            if (item.previewUrl !== null) URL.revokeObjectURL(item.previewUrl);
        });
    }

    function add(files: File[]): void {
        error.value = null;
        const next = [...items.value];

        for (const file of files) {
            const reason = assistantAttachmentRejection(
                file,
                next.map((item) => item.file),
            );
            const kind = assistantAttachmentKind(file);

            if (reason !== null || kind === null) {
                error.value = message(reason ?? 'invalid_type', kind);
                continue;
            }

            next.push({
                id: `a${++counter}`,
                file,
                name: file.name,
                kind,
                previewUrl: kind === 'image' ? URL.createObjectURL(file) : null,
            });
        }

        items.value = next;
    }

    function remove(id: string): void {
        release(items.value.filter((item) => item.id === id));
        items.value = items.value.filter((item) => item.id !== id);
        error.value = null;
    }

    function clear(): void {
        release(items.value);
        items.value = [];
        error.value = null;
    }

    function take(): File[] {
        const files = items.value.map((item) => item.file);
        clear();

        return files;
    }

    onScopeDispose(clear);

    return { items, error, isFull, add, remove, clear, take };
}
