<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import FileInput from '@/Components/Forms/FileInput.vue';

withDefaults(
    defineProps<{
        loading: boolean;
        disabled: boolean;
        error: string | null;
        multiple?: boolean;
    }>(),
    { multiple: false },
);

const emit = defineEmits<{
    preview: [files: File[]];
    change: [];
}>();

const { t } = useI18n();
const files = ref<File[]>([]);

function onFiles(value: File[]): void {
    files.value = value;
    emit('change');
}

function submit(): void {
    if (files.value.length > 0) emit('preview', files.value);
}
</script>

<template>
    <form novalidate class="space-y-2" @submit.prevent="submit">
        <div class="flex items-end gap-2">
            <div class="flex-1 [&_fieldset]:pb-0">
                <FileInput
                    :label="t(multiple ? 'bank_statement_files' : 'bank_statement_file')"
                    accept=".xml,text/xml,application/xml"
                    :multiple="multiple"
                    :disabled="loading || disabled"
                    @update:files="onFiles"
                />
            </div>
            <button type="submit" class="btn btn-neutral" :disabled="loading || disabled || files.length === 0">
                <span v-if="loading" class="loading loading-spinner loading-xs" />
                {{ t('bank_statement_preview') }}
            </button>
        </div>
        <ul v-if="multiple && files.length > 0" class="text-sm text-muted">
            <li v-for="(file, index) in files" :key="`${index}-${file.name}`">{{ file.name }}</li>
        </ul>
    </form>
    <p v-if="error" role="alert" class="text-error text-sm">{{ error }}</p>
</template>
