<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';

import SettingsImageField, { type SettingsImageFieldLabels } from './SettingsImageField.vue';
import type { ImageIntent } from './imageIntent';

const props = withDefaults(
    defineProps<{
        signature: App.Data.Tenants.TenantSignatureData | null;
        constraints: App.Data.Invoices.InvoiceSignatureConstraintsData;
        intent: ImageIntent;
        error?: string | null;
    }>(),
    { error: null },
);

const emit = defineEmits<{ 'update:intent': [value: ImageIntent] }>();

const { t } = useI18n();

const titleId = useId();

const labels = computed<SettingsImageFieldLabels>(() => ({
    empty: t('invoice_settings_signature_empty'),
    upload: t('invoice_settings_signature_upload'),
    replace: t('invoice_settings_signature_replace'),
    remove: t('invoice_settings_signature_remove'),
    pendingUpload: t('invoice_settings_signature_pending_upload'),
    discard: t('invoice_settings_signature_discard'),
    pendingRemove: t('invoice_settings_signature_pending_remove'),
    undoRemove: t('invoice_settings_signature_undo_remove'),
    previewAlt: t('invoice_settings_signature_preview_alt'),
}));
</script>

<template>
    <section class="card bg-base-100 shadow-sm" :aria-labelledby="titleId">
        <div class="card-body space-y-4">
            <h2 :id="titleId" class="card-title text-base">{{ t('invoice_settings_signature_title') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('invoice_settings_signature_hint') }}</p>

            <SettingsImageField
                :image="props.signature"
                :constraints="props.constraints"
                :intent="props.intent"
                :labels="labels"
                :error="props.error"
                @update:intent="emit('update:intent', $event)"
            />
        </div>
    </section>
</template>
