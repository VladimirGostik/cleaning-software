<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import CloseIcon from '~icons/material-symbols/close-outline-rounded';
import DocumentIcon from '~icons/material-symbols/picture-as-pdf-outline-rounded';
import ImageIcon from '~icons/material-symbols/image-outline-rounded';
import type { AssistantAttachmentKind } from '@/utils/assistant';

defineProps<{
    name: string;
    kind: AssistantAttachmentKind;
    previewUrl?: string | null;
    removable?: boolean;
}>();

const emit = defineEmits<{ remove: [] }>();

const { t } = useI18n();
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-1.5 rounded-tile-sm kg-control py-1 pl-2 text-xs"
        :class="removable ? 'pr-1' : 'pr-2'"
    >
        <img v-if="previewUrl" :src="previewUrl" alt="" class="size-6 shrink-0 rounded-tile-sm object-cover" />
        <DocumentIcon v-else-if="kind === 'pdf'" class="text-muted" aria-hidden="true" />
        <ImageIcon v-else class="text-muted" aria-hidden="true" />
        <span class="max-w-40 truncate">{{ name }}</span>
        <button
            v-if="removable"
            type="button"
            class="btn btn-ghost btn-sm btn-circle"
            :aria-label="t('assistant_attachment_remove', { name })"
            :title="t('assistant_attachment_remove', { name })"
            @click="emit('remove')"
        >
            <CloseIcon />
        </button>
    </span>
</template>
