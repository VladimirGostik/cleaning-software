<script setup lang="ts">
import { computed, onMounted, useId, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAutosize } from '@/Composables/useAutosize';

const COUNTER_THRESHOLD = 200;

const props = withDefaults(
    defineProps<{
        modelValue: string;
        label: string;
        placeholder: string;
        maxChars: number;
        error?: string | null;
        disabled?: boolean;
        autofocus?: boolean;
        cancellable?: boolean;
    }>(),
    { error: null, disabled: false, autofocus: false, cancellable: false },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    submit: [];
    cancel: [];
}>();

const { t } = useI18n();
const inputId = useId();
const textarea = useTemplateRef<HTMLTextAreaElement>('textarea');

useAutosize(textarea, () => props.modelValue);

const length = computed(() => props.modelValue.length);
const overLimit = computed(() => length.value > props.maxChars);
const showCounter = computed(() => length.value >= props.maxChars - COUNTER_THRESHOLD);

onMounted(() => {
    if (props.autofocus) textarea.value?.focus();
});

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        emit('submit');
    } else if (event.key === 'Escape' && props.cancellable) {
        event.preventDefault();
        event.stopPropagation();
        emit('cancel');
    }
}
</script>

<template>
    <div class="space-y-1">
        <div class="kg-composer">
            <label :for="inputId" class="sr-only">{{ label }}</label>
            <textarea
                :id="inputId"
                ref="textarea"
                :value="modelValue"
                rows="1"
                class="kg-composer-input"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
                @keydown="onKeydown"
            />
            <slot name="inside" />
        </div>

        <p v-if="showCounter" class="text-xs" :class="overLimit ? 'text-error' : 'text-muted'">
            {{ t('task_comment_chars', { count: length, max: maxChars }) }}
        </p>
        <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
    </div>
</template>
