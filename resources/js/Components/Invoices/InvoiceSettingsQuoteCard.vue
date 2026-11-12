<script setup lang="ts">
import { useId } from 'vue';
import { useI18n } from 'vue-i18n';

import TextInput from '@/Components/Forms/TextInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';

const includedText = defineModel<string | null>('includedText', { required: true });

const props = withDefaults(defineProps<{ includedError?: string | null }>(), { includedError: null });

const { t } = useI18n();

const titleId = useId();
</script>

<template>
    <section class="card bg-base-100" :aria-labelledby="titleId">
        <div class="card-body space-y-4">
            <h2 :id="titleId" class="card-title">{{ t('invoice_settings_section_quotes') }}</h2>
            <p class="text-sm text-muted">{{ t('invoice_settings_quotes_hint') }}</p>

            <TextareaInput
                :model-value="includedText ?? ''"
                :label="t('invoice_settings_default_quote_included_text')"
                :rows="4"
                :error="props.includedError"
                @update:model-value="includedText = $event || null"
            />
            <p class="text-xs text-muted">{{ t('quote_included_hint') }}</p>

            <div>
                <TextInput field="signing_place" :label="t('invoice_settings_signing_place')" maxlength="100" />
                <p class="mt-1 text-xs text-muted">{{ t('invoice_settings_signing_place_hint') }}</p>
            </div>
        </div>
    </section>
</template>
