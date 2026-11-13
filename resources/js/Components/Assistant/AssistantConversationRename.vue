<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps<{
    initial: string;
    error: string | null;
    saving: boolean;
}>();

const emit = defineEmits<{
    save: [title: string];
    cancel: [];
}>();

const { t } = useI18n();
const root = useTemplateRef<HTMLElement>('root');
const title = ref(props.initial);

function submit(): void {
    const trimmed = title.value.trim();
    if (trimmed !== '') emit('save', trimmed);
}

onMounted(() => root.value?.querySelector('input')?.focus());
</script>

<template>
    <form ref="root" class="flex flex-col gap-2 py-2" @submit.prevent="submit">
        <TextInput
            v-model="title"
            :label="t('assistant_conversation_title_label')"
            :aria-label="t('assistant_conversation_title_label')"
            :error="error"
            :disabled="saving"
            maxlength="120"
            @keydown.esc.stop="emit('cancel')"
        />
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" :disabled="saving" @click="emit('cancel')">
                {{ t('cancel') }}
            </button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="saving || title.trim() === ''">
                {{ t('save') }}
            </button>
        </div>
    </form>
</template>
