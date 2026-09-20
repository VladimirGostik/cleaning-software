<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import FormField from '@/Components/Forms/FormField.vue';
import FileUploadInput from '@/Components/Forms/FileUploadInput.vue';
import InvoiceSignaturePreview from './InvoiceSignaturePreview.vue';
import type { SignatureIntent } from './signatureIntent';

const props = withDefaults(
    defineProps<{
        signature: App.Data.Tenants.TenantSignatureData | null;
        constraints: App.Data.Invoices.InvoiceSignatureConstraintsData;
        intent: SignatureIntent;
        error?: string | null;
    }>(),
    { error: null },
);

const emit = defineEmits<{ 'update:intent': [value: SignatureIntent] }>();

const { t } = useI18n();

const titleId = useId();
const showReplace = ref(false);

const accept = computed(() => props.constraints.allowed_mimes.join(','));

const uploadModelValue = computed<string | null>(() => (props.intent.kind === 'upload' ? props.intent.uuid : null));

function onUploaded(value: string | string[] | null): void {
    const uuid = Array.isArray(value) ? (value[0] ?? null) : value;
    emit('update:intent', uuid === null ? { kind: 'keep' } : { kind: 'upload', uuid });
    if (uuid === null) {
        showReplace.value = false;
    }
}

function onRemove(): void {
    emit('update:intent', { kind: 'remove' });
}

function onDiscardUpload(): void {
    emit('update:intent', { kind: 'keep' });
    showReplace.value = false;
}

function onUndoRemove(): void {
    emit('update:intent', { kind: 'keep' });
}

watch(
    () => props.signature,
    () => {
        showReplace.value = false;
    },
);
</script>

<template>
    <section class="card bg-base-100 shadow-sm" :aria-labelledby="titleId">
        <div class="card-body space-y-4">
            <h2 :id="titleId" class="card-title text-base">{{ t('invoice_settings_signature_title') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('invoice_settings_signature_hint') }}</p>

            <!-- EMPTY -->
            <template v-if="intent.kind === 'keep' && signature === null">
                <p class="text-sm text-base-content/60">{{ t('invoice_settings_signature_empty') }}</p>
                <FormField :label="t('invoice_settings_signature_upload')" :error="error">
                    <FileUploadInput
                        :model-value="uploadModelValue"
                        :accept="accept"
                        :max-size-kb="constraints.max_size_kb"
                        endpoint="/uploads"
                        :error="error"
                        @update:model-value="onUploaded"
                    />
                </FormField>
            </template>

            <!-- SAVED -->
            <template v-else-if="intent.kind === 'keep' && signature !== null">
                <InvoiceSignaturePreview :signature="signature" />

                <FormField v-if="showReplace" :label="t('invoice_settings_signature_replace')" :error="error">
                    <FileUploadInput
                        :model-value="uploadModelValue"
                        :accept="accept"
                        :max-size-kb="constraints.max_size_kb"
                        endpoint="/uploads"
                        :error="error"
                        @update:model-value="onUploaded"
                    />
                </FormField>

                <div class="flex flex-wrap gap-2">
                    <button v-if="!showReplace" type="button" class="btn btn-sm" @click="showReplace = true">
                        {{ t('invoice_settings_signature_replace') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-ghost text-error" @click="onRemove">
                        {{ t('invoice_settings_signature_remove') }}
                    </button>
                </div>
            </template>

            <!-- STAGED UPLOAD -->
            <template v-else-if="intent.kind === 'upload'">
                <FormField :label="t('invoice_settings_signature_upload')" :error="error">
                    <FileUploadInput
                        :model-value="uploadModelValue"
                        :accept="accept"
                        :max-size-kb="constraints.max_size_kb"
                        endpoint="/uploads"
                        :error="error"
                        @update:model-value="onUploaded"
                    />
                </FormField>
                <p role="status" class="text-sm text-base-content/60">
                    {{ t('invoice_settings_signature_pending_upload') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-ghost" @click="onDiscardUpload">
                        {{ t('invoice_settings_signature_discard') }}
                    </button>
                </div>
            </template>

            <!-- STAGED REMOVAL -->
            <template v-else-if="intent.kind === 'remove'">
                <InvoiceSignaturePreview v-if="signature !== null" :signature="signature" muted />
                <div role="status" aria-live="polite" class="alert alert-warning">
                    <span>{{ t('invoice_settings_signature_pending_remove') }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" @click="onUndoRemove">
                        {{ t('invoice_settings_signature_undo_remove') }}
                    </button>
                </div>
            </template>
        </div>
    </section>
</template>
