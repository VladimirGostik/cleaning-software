<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';

const headerText = defineModel<string | null>('headerText', { required: true });
const footerText = defineModel<string | null>('footerText', { required: true });

withDefaults(
    defineProps<{
        headerError?: string | null;
        footerError?: string | null;
    }>(),
    { headerError: undefined, footerError: undefined },
);

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_settings_section_texts') }}</h2>
            <p class="text-sm text-base-content/60">{{ t('invoice_settings_texts_hint') }}</p>

            <TextareaInput
                :model-value="headerText ?? ''"
                :label="t('invoice_settings_default_header_text')"
                :rows="3"
                :error="headerError"
                @update:model-value="headerText = $event || null"
            />

            <TextareaInput
                :model-value="footerText ?? ''"
                :label="t('invoice_settings_default_footer_text')"
                :rows="3"
                :error="footerError"
                @update:model-value="footerText = $event || null"
            />
        </div>
    </div>
</template>
