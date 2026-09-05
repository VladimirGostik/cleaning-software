<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
    import FileDropInput from '@/Components/Forms/FileDropInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { DOCUMENT_ALLOWED_MIMES, DOCUMENT_MAX_SIZE_KB, formatFileSize } from '@/lib/documentUpload';

    const props = defineProps<{
        quoteId: string;
        document: App.Data.Media.MediaFileData | null;
        canUpload: boolean;
    }>();

    const { t } = useTranslate();

    const form = useForm('post', `/quotes/${props.quoteId}/document`, {
        document: null as File | null,
    });

    const ui = reactive({ replacing: false, clientError: null as string | null });

    const progress = computed<number | null>(() => form.progress?.percentage ?? null);

    function onModelUpdate(file: File | null): void {
        form.document = file;
        ui.clientError = null;
    }

    function onInvalid(reason: 'mime' | 'size'): void {
        ui.clientError = t(reason === 'mime' ? 'quotes.document.error_mime' : 'quotes.document.error_size');
    }

    function cancelReplace(): void {
        ui.replacing = false;
        ui.clientError = null;
        form.reset();
        form.clearErrors();
    }

    function submit(): void {
        form.submit({
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                ui.replacing = false;
                ui.clientError = null;
            },
        });
    }
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-3">
            <h2 class="card-title text-sm">{{ t('quotes.section.document') }}</h2>

            <!-- Has a document, not replacing -->
            <div
                v-if="document && !ui.replacing"
                class="flex items-center justify-between gap-3 text-sm bg-base-200/50 rounded-lg p-3"
            >
                <div class="min-w-0">
                    <p class="font-medium truncate">{{ document.file_name }}</p>
                    <p class="text-xs text-base-content/50">
                        {{ document.mime_type ?? '—' }} · {{ formatFileSize(document.size) }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a :href="document.download_url" target="_blank" class="btn btn-sm">
                        {{ t('quotes.document.download') }}
                    </a>
                    <button
                        v-if="canUpload"
                        type="button"
                        class="btn btn-ghost btn-sm"
                        @click="ui.replacing = true"
                    >
                        {{ t('quotes.document.replace') }}
                    </button>
                </div>
            </div>

            <!-- Has a document, replacing -->
            <form v-else-if="document && ui.replacing" novalidate class="space-y-3" @submit.prevent="submit">
                <FileDropInput
                    :model-value="form.document"
                    :accept="DOCUMENT_ALLOWED_MIMES"
                    :max-size-kb="DOCUMENT_MAX_SIZE_KB"
                    :hint="t('quotes.document.drop_hint')"
                    :choose-label="t('quotes.document.choose_file')"
                    :remove-label="t('quotes.document.remove_file')"
                    :error="ui.clientError ?? form.errors.document"
                    :progress="progress"
                    :current-file-name="document.file_name"
                    required
                    @update:model-value="onModelUpdate"
                    @invalid="onInvalid"
                />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="cancelReplace">
                        {{ t('cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary btn-sm"
                        :disabled="form.processing || !form.document"
                    >
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                        {{ t('save') }}
                    </button>
                </div>
            </form>

            <!-- No document — self-healing state (F3 braces) -->
            <template v-else>
                <div class="alert alert-warning">
                    <ExclamationTriangleIcon class="w-5 h-5 shrink-0" />
                    <div>
                        <p class="font-medium">{{ t('quotes.document.missing_title') }}</p>
                        <p class="text-sm">{{ t('quotes.document.missing_hint') }}</p>
                    </div>
                </div>

                <form v-if="canUpload" novalidate class="space-y-3" @submit.prevent="submit">
                    <FileDropInput
                        :model-value="form.document"
                        :accept="DOCUMENT_ALLOWED_MIMES"
                        :max-size-kb="DOCUMENT_MAX_SIZE_KB"
                        :hint="t('quotes.document.drop_hint')"
                        :choose-label="t('quotes.document.choose_file')"
                        :remove-label="t('quotes.document.remove_file')"
                        :error="ui.clientError ?? form.errors.document"
                        :progress="progress"
                        required
                        @update:model-value="onModelUpdate"
                        @invalid="onInvalid"
                    />
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="btn btn-primary btn-sm"
                            :disabled="form.processing || !form.document"
                        >
                            <span v-if="form.processing" class="loading loading-spinner loading-xs" />
                            {{ t('save') }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</template>
