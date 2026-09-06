<script setup lang="ts">
import { ref } from 'vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import PlaceholderTokenList from './PlaceholderTokenList.vue';

const props = defineProps<{
    modelValue: string;
    error: string | null;
    tokens: readonly App.Data.Contracts.PlaceholderTokenData[];
    label: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    hint?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editorRef = ref<InstanceType<typeof TextareaInput> | null>(null);

function onInsert(token: string): void {
    editorRef.value?.insertAtCursor(`{{${token}}}`);
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_260px]">
        <div>
            <TextareaInput
                ref="editorRef"
                :model-value="props.modelValue"
                :label="label"
                :placeholder="placeholder"
                :error="error"
                :required="required"
                :disabled="disabled"
                :rows="24"
                @update:model-value="emit('update:modelValue', $event)"
            />
            <p v-if="hint" class="text-xs text-base-content/60">{{ hint }}</p>
        </div>

        <PlaceholderTokenList class="lg:sticky lg:top-4 self-start" :tokens="tokens" @insert="onInsert" />
    </div>
</template>
