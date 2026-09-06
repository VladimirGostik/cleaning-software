<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { XMarkIcon } from '@heroicons/vue/24/outline';

import InvoiceTemplateThumbnail from './InvoiceTemplateThumbnail.vue';
import { INVOICE_TEMPLATES, invoiceTemplateKey } from '@/utils/enums';

const props = withDefaults(
    defineProps<{
        modelValue: App.Enums.InvoiceTemplateEnum;
        error?: string | null;
    }>(),
    { error: null },
);

const emit = defineEmits<{
    'update:modelValue': [value: App.Enums.InvoiceTemplateEnum];
}>();

const { t } = useI18n();

const ui = reactive({
    previewOpen: false,
    previewTemplate: null as App.Enums.InvoiceTemplateEnum | null,
});

const options = computed(() => INVOICE_TEMPLATES.map((value) => ({ value, label: t(invoiceTemplateKey(value)) })));

const iframeSrc = computed(() => (ui.previewTemplate ? `/settings/invoicing/preview/${ui.previewTemplate}` : ''));

function selectTemplate(value: App.Enums.InvoiceTemplateEnum): void {
    emit('update:modelValue', value);
    ui.previewTemplate = value;
    ui.previewOpen = true;
}

function closePreview(): void {
    ui.previewOpen = false;
    ui.previewTemplate = null;
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="t('invoice_settings_section_templates')">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="props.modelValue === option.value"
                class="flex w-24 cursor-pointer flex-col items-center rounded-lg border-2 p-2 transition focus:ring-2 focus:ring-primary focus:outline-none"
                :class="
                    props.modelValue === option.value
                        ? 'border-primary bg-primary/5'
                        : 'border-base-300 hover:border-base-content/30'
                "
                @click="selectTemplate(option.value)"
                @keydown.enter.prevent="selectTemplate(option.value)"
                @keydown.space.prevent="selectTemplate(option.value)"
            >
                <InvoiceTemplateThumbnail :template="option.value" class="h-16 w-12" />
                <p class="mt-1 text-center text-xs leading-tight font-medium">{{ option.label }}</p>
                <p v-if="props.modelValue === option.value" class="mt-0.5 text-center text-xs text-primary">
                    {{ t('invoice_settings_template_selected') }}
                </p>
            </button>
        </div>

        <p v-if="props.error" class="text-error text-sm">{{ props.error }}</p>

        <a
            :href="`/settings/invoicing/preview/${props.modelValue}`"
            target="_blank"
            rel="noopener"
            class="link link-hover text-sm"
        >
            {{ t('invoice_settings_open_preview') }}
        </a>

        <div v-if="ui.previewOpen" class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-md">
            <div class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                <span class="text-sm font-medium">{{ t('invoice_settings_template_preview') }}</span>
                <button
                    type="button"
                    class="btn btn-circle btn-ghost btn-sm"
                    :aria-label="t('invoice_settings_close_preview')"
                    :title="t('invoice_settings_close_preview')"
                    @click="closePreview"
                >
                    <XMarkIcon class="size-4" />
                </button>
            </div>
            <iframe
                :src="iframeSrc"
                :title="t('invoice_settings_template_preview')"
                sandbox="allow-same-origin"
                class="h-[450px] w-full rounded-b border-0"
            />
        </div>
    </div>
</template>
