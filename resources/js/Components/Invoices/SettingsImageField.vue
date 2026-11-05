<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import FormField from '@/Components/Forms/FormField.vue';
import FileUploadInput from '@/Components/Forms/FileUploadInput.vue';
import SettingsImagePreview, { type SettingsImage } from './SettingsImagePreview.vue';
import type { ImageIntent } from './imageIntent';

export interface SettingsImageFieldLabels {
    empty: string;
    upload: string;
    replace: string;
    remove: string;
    pendingUpload: string;
    discard: string;
    pendingRemove: string;
    undoRemove: string;
    previewAlt: string;
}

const props = withDefaults(
    defineProps<{
        image: SettingsImage | null;
        constraints: { allowed_mimes: string[]; max_size_kb: number };
        intent: ImageIntent;
        labels: SettingsImageFieldLabels;
        error?: string | null;
    }>(),
    { error: null },
);

const emit = defineEmits<{ 'update:intent': [value: ImageIntent] }>();

const showReplace = ref(false);

const accept = computed(() => props.constraints.allowed_mimes.join(','));

const uploadModelValue = computed<string | null>(() => (props.intent.kind === 'upload' ? props.intent.uuid : null));

function onUploaded(value: string | string[] | null): void {
    const uuid = Array.isArray(value) ? (value[0] ?? null) : value;
    emit('update:intent', uuid === null ? { kind: 'keep' } : { kind: 'upload', uuid });
    if (uuid === null) {
        showReplace.value = false;
    }
}

function onRemove(): void {
    emit('update:intent', { kind: 'remove' });
}

function onDiscardUpload(): void {
    emit('update:intent', { kind: 'keep' });
    showReplace.value = false;
}

function onUndoRemove(): void {
    emit('update:intent', { kind: 'keep' });
}

watch(
    () => props.image,
    () => {
        showReplace.value = false;
    },
);
</script>

<template>
    <div class="space-y-4">
        <template v-if="intent.kind === 'keep' && image === null">
            <p class="text-sm text-base-content/60">{{ labels.empty }}</p>
            <FormField :label="labels.upload" :error="error">
                <FileUploadInput
                    :model-value="uploadModelValue"
                    :accept="accept"
                    :max-size-kb="constraints.max_size_kb"
                    endpoint="/uploads"
                    :error="error"
                    @update:model-value="onUploaded"
                />
            </FormField>
        </template>

        <template v-else-if="intent.kind === 'keep' && image !== null">
            <SettingsImagePreview :image="image" :alt="labels.previewAlt" />

            <FormField v-if="showReplace" :label="labels.replace" :error="error">
                <FileUploadInput
                    :model-value="uploadModelValue"
                    :accept="accept"
                    :max-size-kb="constraints.max_size_kb"
                    endpoint="/uploads"
                    :error="error"
                    @update:model-value="onUploaded"
                />
            </FormField>

            <div class="flex flex-wrap gap-2">
                <button v-if="!showReplace" type="button" class="btn btn-sm" @click="showReplace = true">
                    {{ labels.replace }}
                </button>
                <button type="button" class="btn btn-sm btn-ghost text-error" @click="onRemove">
                    {{ labels.remove }}
                </button>
            </div>
        </template>

        <template v-else-if="intent.kind === 'upload'">
            <FormField :label="labels.upload" :error="error">
                <FileUploadInput
                    :model-value="uploadModelValue"
                    :accept="accept"
                    :max-size-kb="constraints.max_size_kb"
                    endpoint="/uploads"
                    :error="error"
                    @update:model-value="onUploaded"
                />
            </FormField>
            <p role="status" class="text-sm text-base-content/60">{{ labels.pendingUpload }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-ghost" @click="onDiscardUpload">
                    {{ labels.discard }}
                </button>
            </div>
        </template>

        <template v-else-if="intent.kind === 'remove'">
            <SettingsImagePreview v-if="image !== null" :image="image" :alt="labels.previewAlt" muted />
            <div role="status" aria-live="polite" class="alert alert-warning">
                <span>{{ labels.pendingRemove }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm" @click="onUndoRemove">{{ labels.undoRemove }}</button>
            </div>
        </template>
    </div>
</template>
