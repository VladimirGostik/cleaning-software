<script lang="ts">
export interface AttachedFileConstraints {
    allowed_mimes: string[];
    max_size_kb: number;
    allowed_extensions?: string[];
    types?: App.Enums.DocumentTypeEnum[];
    max_count?: number;
}
</script>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import FormField from '@/Components/Forms/FormField.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { documentTypeKey, enumOptions } from '@/utils/enums';
import { fileAccept } from '@/utils/contracts';
import { formatBytes } from '@/utils/bytes';

interface UploadFormData {
    type: App.Enums.DocumentTypeEnum | '';
    title: string;
    file: File | null;
}

const props = defineProps<{
    uploadUrl: string;
    constraints: AttachedFileConstraints;
    titleMaxLength: number;
}>();

const { t } = useI18n();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<UploadFormData>('post', props.uploadUrl, {
    type: '',
    title: '',
    file: null,
});

form.transform((data: UploadFormData) => ({
    ...(props.constraints.types ? { type: data.type } : {}),
    title: data.title || null,
    file: data.file,
}));

const typeOptions = computed(() =>
    props.constraints.types ? enumOptions(props.constraints.types, documentTypeKey, t) : null,
);
const accept = computed(() => fileAccept(props.constraints));
const maxSize = computed(() => formatBytes(props.constraints.max_size_kb * 1024));

function onFileChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.clearErrors('file');

    if (file && file.size > props.constraints.max_size_kb * 1024) {
        form.file = null;
        if (fileInput.value) fileInput.value.value = '';
        form.setError('file', t('attached_file_too_large', { size: maxSize.value }));
        return;
    }

    form.file = file;
}

function submit(): void {
    if (!form.file) {
        form.setError('file', t('attached_file_file_required'));
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
        <form novalidate class="kg-card-nested space-y-3 p-4" @submit.prevent="submit">
            <h3 class="text-base font-medium">{{ t('attached_file_upload') }}</h3>

            <div class="grid grid-cols-1 gap-3" :class="{ 'md:grid-cols-2': typeOptions }">
                <SelectInput
                    v-if="typeOptions"
                    field="type"
                    required
                    :label="t('type')"
                    :options="typeOptions"
                    :placeholder="t('select_type')"
                />
                <TextInput field="title" :maxlength="titleMaxLength" :label="t('attached_file_title')" />
            </div>

            <FormField :label="t('attached_file_file')" required :error="form.errors.file">
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
                <p class="mt-1 text-xs text-muted">
                    {{ t('attached_file_hint', { size: maxSize }) }}
                </p>
            </FormField>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
                    <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                    {{ t('attached_file_upload_submit') }}
                </button>
            </div>
        </form>
    </FormProvider>
</template>
