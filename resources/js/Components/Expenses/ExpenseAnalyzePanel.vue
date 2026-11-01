<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import FileInput from '@/Components/Forms/FileInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import { useExpenseAnalyze } from '@/Composables/useExpenseAnalyze';

const emit = defineEmits<{
    analyzed: [draft: App.Data.Expenses.ExpenseDraftData, file: File | null];
}>();

const { t } = useI18n();
const { loading, error, analyze } = useExpenseAnalyze();

const file = ref<File | null>(null);
const text = ref('');

async function run(): Promise<void> {
    const draft = await analyze(file.value ? { file: file.value } : { text: text.value });
    if (draft) emit('analyzed', draft, file.value);
}
</script>

<template>
    <div class="space-y-3 rounded-box border border-base-300 p-4">
        <h2 class="text-sm font-semibold">{{ t('expense_analyze_title') }}</h2>
        <p class="text-xs text-base-content/60">{{ t('expense_analyze_hint') }}</p>

        <FileInput
            :label="t('expense_analyze_file')"
            accept="application/pdf,image/jpeg,image/png,image/webp"
            @update:model-value="file = $event"
        />

        <TextareaInput v-if="!file" v-model="text" :label="t('expense_analyze_text')" :rows="3" />

        <p v-if="error" class="text-sm text-error">{{ error }}</p>

        <button
            type="button"
            class="btn btn-sm btn-outline"
            :disabled="loading || (!file && text.trim() === '')"
            @click="run"
        >
            <span v-if="loading" class="loading loading-spinner loading-xs" />
            {{ t('expense_analyze_run') }}
        </button>
    </div>
</template>
