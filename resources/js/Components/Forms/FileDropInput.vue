<script setup lang="ts">
    import { reactive } from 'vue';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import FormField from './FormField.vue';
    import { formatFileSize } from '@/lib/documentUpload';

    /**
     * Generic file picker — presentational, HTTP-free. Knows about `File`,
     * mime types and size limits only. This is the shared Phase-2 convention
     * for cleaning photos / contract attachments / complaints — do not add
     * quote-specific (or any domain-specific) knowledge here.
     */
    const props = withDefaults(
        defineProps<{
            modelValue: File | null;
            accept: readonly string[];
            maxSizeKb: number;
            label?: string;
            hint?: string;
            chooseLabel?: string;
            removeLabel?: string;
            error?: string;
            required?: boolean;
            disabled?: boolean;
            progress?: number | null;
            currentFileName?: string | null;
        }>(),
        {
            label: undefined,
            hint: undefined,
            chooseLabel: undefined,
            removeLabel: undefined,
            error: undefined,
            required: false,
            disabled: false,
            progress: null,
            currentFileName: null,
        },
    );

    const emit = defineEmits<{
        'update:modelValue': [value: File | null];
        invalid: [reason: 'mime' | 'size'];
    }>();

    const ui = reactive({ dragging: false });

    function validate(file: File | null): void {
        if (!file) {
            emit('update:modelValue', null);
            return;
        }
        if (!props.accept.includes(file.type)) {
            emit('invalid', 'mime');
            return;
        }
        if (file.size / 1024 > props.maxSizeKb) {
            emit('invalid', 'size');
            return;
        }
        emit('update:modelValue', file);
    }

    function onChange(e: Event): void {
        const input = e.target as HTMLInputElement;
        const file = input.files?.[0] ?? null;
        // Reset so re-picking the identical file still fires `change`.
        input.value = '';
        validate(file);
    }

    function onDrop(e: DragEvent): void {
        ui.dragging = false;
        if (props.disabled) return;
        validate(e.dataTransfer?.files?.[0] ?? null);
    }

    function clear(): void {
        emit('update:modelValue', null);
    }
</script>

<template>
    <FormField :label="label" :error="error" :required="required">
        <label
            class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-base-300 p-6 text-center transition-colors focus-within:ring-2 focus-within:ring-primary"
            :class="[
                disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                { 'border-primary bg-primary/5': ui.dragging },
            ]"
            @dragover.prevent="!disabled && (ui.dragging = true)"
            @dragleave.prevent="ui.dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                type="file"
                class="sr-only"
                :accept="accept.join(',')"
                :required="required"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                @change="onChange"
            />
            <span v-if="hint" class="text-sm text-base-content/70">{{ hint }}</span>
            <span class="btn btn-sm btn-outline">{{ chooseLabel }}</span>
        </label>

        <div
            v-if="modelValue"
            class="flex items-center justify-between gap-2 mt-2 text-sm bg-base-200/50 rounded-lg px-3 py-2"
        >
            <span class="truncate">{{ modelValue.name }} · {{ formatFileSize(modelValue.size) }}</span>
            <button
                type="button"
                class="btn btn-ghost btn-xs"
                :aria-label="removeLabel"
                @click.prevent="clear"
            >
                <XMarkIcon class="w-4 h-4" />
            </button>
        </div>
        <p v-else-if="currentFileName" class="text-xs text-base-content/50 mt-1 truncate">
            {{ currentFileName }}
        </p>

        <progress
            v-if="progress !== null"
            class="progress progress-primary w-full mt-2"
            :value="progress"
            max="100"
            role="progressbar"
            :aria-valuenow="progress"
        />
    </FormField>
</template>
