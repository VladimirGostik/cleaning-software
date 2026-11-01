<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import FormField from '@/Components/Forms/FormField.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { documentTypeKey, enumOptions } from '@/utils/enums';
import { formatBytes } from '@/utils/bytes';

interface UploadFormData {
    type: App.Enums.DocumentTypeEnum | '';
    title: string;
    file: File | null;
}

const props = defineProps<{
    employeeId: string;
    constraints: App.Data.Employees.EmployeeDocumentConstraintsData;
}>();

const { t } = useI18n();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<UploadFormData>('post', `/employees/${props.employeeId}/documents`, {
    type: '',
    title: '',
    file: null,
});

form.transform((data: UploadFormData) => ({
    type: data.type,
    title: data.title || null,
    file: data.file,
}));

const typeOptions = computed(() => enumOptions(props.constraints.types, documentTypeKey, t));
const accept = computed(() => props.constraints.allowed_mimes.join(','));
const maxSize = computed(() => formatBytes(props.constraints.max_size_kb * 1024));

function onFileChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.clearErrors('file');

    if (file && file.size > props.constraints.max_size_kb * 1024) {
        form.file = null;
        if (fileInput.value) fileInput.value.value = '';
        form.setError('file', t('employee_document_too_large', { size: maxSize.value }));
        return;
    }

    form.file = file;
}

function submit(): void {
    if (!form.file) {
        form.setError('file', t('employee_document_file_required'));
        return;
    }

    form.submit({
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-3 rounded-box border border-base-200 p-4" @submit.prevent="submit">
            <h3 class="text-sm font-semibold">{{ t('employee_document_upload') }}</h3>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <SelectInput
                    field="type"
                    required
                    :label="t('type')"
                    :options="typeOptions"
                    :placeholder="t('select_type')"
                />
                <TextInput field="title" maxlength="255" :label="t('employee_document_title')" />
            </div>

            <FormField :label="t('employee_document_file')" required :error="form.errors.file">
                <input
                    ref="fileInput"
                    type="file"
                    class="file-input w-full"
                    :class="{ 'file-input-error': form.errors.file }"
                    :accept="accept"
                    :disabled="form.processing"
                    :aria-invalid="form.errors.file ? 'true' : undefined"
                    @change="onFileChange"
                />
                <p class="mt-1 text-xs text-base-content/60">
                    {{ t('employee_document_hint', { size: maxSize }) }}
                </p>
            </FormField>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                    {{ t('employee_document_upload_submit') }}
                </button>
            </div>
        </form>
    </FormProvider>
</template>
