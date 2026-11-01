<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import FileInput from '@/Components/Forms/FileInput.vue';
import ExpenseImportTable from '@/Components/Expenses/ExpenseImportTable.vue';

import { useExpenseImportPreview } from '@/Composables/useExpenseImportPreview';
import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Expenses.ExpenseFormContextData;
}>();

const { t } = useI18n();
const { preview, loading, error, load } = useExpenseImportPreview();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('expenses'), url: '/expenses' },
    { label: t('expense_import') },
];

const file = ref<File | null>(null);
const rows = ref<App.Data.Expenses.ExpenseImportRowData[]>([]);
const submitting = ref(false);
const submitErrors = ref<string[]>([]);

const selectedCount = computed(() => rows.value.filter((r) => r.include).length);

async function runPreview(): Promise<void> {
    if (!file.value) return;
    await load(file.value);
    rows.value = (preview.value?.rows ?? []).map((r) => ({ ...r }));
}

function submit(): void {
    if (!preview.value) return;

    router.post(
        '/expenses/import',
        { mapping: { ...preview.value.mapping }, rows: rows.value.map((r) => ({ ...r })) },
        {
            onStart: () => {
                submitting.value = true;
                submitErrors.value = [];
            },
            onFinish: () => (submitting.value = false),
            onError: (errors: Record<string, string>) => {
                submitErrors.value = Object.values(errors);
            },
        },
    );
}
</script>

<template>
    <Header :title="t('expense_import')" :breadcrumbs="breadcrumbs" />

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <form novalidate class="flex items-end gap-2" @submit.prevent="runPreview">
                <div class="flex-1">
                    <FileInput
                        :label="t('expense_import_file')"
                        accept=".csv,text/csv,text/plain"
                        :error="error"
                        @update:model-value="file = $event"
                    />
                </div>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="loading || !file">
                    <span v-if="loading" class="loading loading-spinner loading-xs" />
                    {{ t('expense_import_preview') }}
                </button>
            </form>

            <template v-if="preview">
                <p v-if="preview.skipped_credit_rows > 0" class="text-sm text-base-content/60">
                    {{ t('expense_import_skipped_credit') }}: {{ preview.skipped_credit_rows }}
                </p>

                <ExpenseImportTable :rows="rows" />

                <ul v-if="submitErrors.length > 0" class="text-sm text-error">
                    <li v-for="message in submitErrors" :key="message">{{ message }}</li>
                </ul>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="btn btn-primary"
                        :disabled="submitting || selectedCount === 0"
                        @click="submit"
                    >
                        <span v-if="submitting" class="loading loading-spinner loading-xs" />
                        {{ t('expense_import_submit') }} ({{ selectedCount }})
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
