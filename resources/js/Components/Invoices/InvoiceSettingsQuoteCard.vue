<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';

import BrandLogo from '@/Components/BrandLogo.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import SettingsImageField, { type SettingsImageFieldLabels } from './SettingsImageField.vue';
import type { ImageIntent } from './imageIntent';

const includedText = defineModel<string | null>('includedText', { required: true });

const props = withDefaults(
    defineProps<{
        logo: App.Data.Tenants.TenantLogoData | null;
        constraints: App.Data.Invoices.TenantLogoConstraintsData;
        intent: ImageIntent;
        logoError?: string | null;
        includedError?: string | null;
    }>(),
    { logoError: null, includedError: null },
);

const emit = defineEmits<{ 'update:intent': [value: ImageIntent] }>();

const { t } = useI18n();

const titleId = useId();

const labels = computed<SettingsImageFieldLabels>(() => ({
    empty: t('invoice_settings_logo_empty'),
    upload: t('invoice_settings_logo_upload'),
    replace: t('invoice_settings_logo_replace'),
    remove: t('invoice_settings_logo_remove'),
    pendingUpload: t('invoice_settings_logo_pending_upload'),
    discard: t('invoice_settings_logo_discard'),
    pendingRemove: t('invoice_settings_logo_pending_remove'),
    undoRemove: t('invoice_settings_logo_undo_remove'),
    previewAlt: t('invoice_settings_logo_preview_alt'),
}));
</script>

<template>
    <section class="card bg-base-100 shadow-sm" :aria-labelledby="titleId">
        <div class="card-body space-y-4">
            <h2 :id="titleId" class="card-title text-base">{{ t('invoice_settings_section_quotes') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('invoice_settings_quotes_hint') }}</p>

            <div class="space-y-2">
                <h3 class="text-sm font-medium">{{ t('invoice_settings_logo_title') }}</h3>
                <p class="text-sm text-base-content/60">{{ t('invoice_settings_logo_hint') }}</p>
                <SettingsImageField
                    :image="props.logo"
                    :constraints="props.constraints"
                    :intent="props.intent"
                    :labels="labels"
                    :error="props.logoError"
                    @update:intent="emit('update:intent', $event)"
                >
                    <template #empty>
                        <div class="flex items-center gap-3 rounded-box border border-base-300 bg-base-200 p-3">
                            <BrandLogo :size="28" />
                            <span class="text-xs text-base-content/60">{{ t('invoice_settings_logo_default') }}</span>
                        </div>
                    </template>
                </SettingsImageField>
            </div>

            <TextareaInput
                :model-value="includedText ?? ''"
                :label="t('invoice_settings_default_quote_included_text')"
                :rows="4"
                :error="props.includedError"
                @update:model-value="includedText = $event || null"
            />
            <p class="text-xs text-base-content/60">{{ t('quote_included_hint') }}</p>

            <div>
                <TextInput field="signing_place" :label="t('invoice_settings_signing_place')" maxlength="100" />
                <p class="mt-1 text-xs text-base-content/60">{{ t('invoice_settings_signing_place_hint') }}</p>
            </div>
        </div>
    </section>
</template>
