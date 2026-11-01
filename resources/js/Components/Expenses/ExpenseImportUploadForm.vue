<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FileInput from '@/Components/Forms/FileInput.vue';

defineProps<{
    loading: boolean;
    disabled: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    preview: [file: File];
    change: [];
}>();

const { t } = useI18n();
const file = ref<File | null>(null);

function onFile(value: File | null): void {
    file.value = value;
    emit('change');
}

function submit(): void {
    if (file.value) emit('preview', file.value);
}
</script>

<template>
    <form novalidate class="flex items-end gap-2" @submit.prevent="submit">
        <div class="flex-1 [&_fieldset]:pb-0">
            <FileInput
                :label="t('expense_import_file')"
                accept=".xml,text/xml,application/xml"
                :disabled="loading || disabled"
                @update:model-value="onFile"
            />
        </div>
        <button type="submit" class="btn btn-neutral" :disabled="loading || disabled || !file">
            <span v-if="loading" class="loading loading-spinner loading-xs" />
            {{ t('expense_import_preview') }}
        </button>
    </form>
    <p v-if="error" role="alert" class="text-error text-sm">{{ error }}</p>
</template>
